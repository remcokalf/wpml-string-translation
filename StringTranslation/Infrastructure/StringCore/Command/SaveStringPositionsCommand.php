<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Command\SaveStringPositionsCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Domain\StringPosition;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\DomainValueAndContextCriteria;
use WPML\StringTranslation\Application\StringCore\Query\FindByDomainValueAndContextQueryInterface;
use WPML\StringTranslation\Application\StringCore\Command\InsertStringPositionsCommandInterface;

class SaveStringPositionsCommand implements SaveStringPositionsCommandInterface {

	private $findByDomainValueAndContextQuery;

	private $insertStringPositionsCommand;

	public function __construct(
		FindByDomainValueAndContextQueryInterface $findByDomainValueAndContextQuery,
		InsertStringPositionsCommandInterface     $insertStringPositionsCommand
	) {
		$this->findByDomainValueAndContextQuery = $findByDomainValueAndContextQuery;
		$this->insertStringPositionsCommand     = $insertStringPositionsCommand;
	}

	private function findStringFieldsByDomainValueAndContext( array $strings, array $fields ): array {
		$criteria = new DomainValueAndContextCriteria( $strings, $fields );
		return $this->findByDomainValueAndContextQuery->execute( $criteria );
	}

	public function run( array $strings ) {
		$strings = $this->findStringFieldsByDomainValueAndContext( $strings, ['id', 'matching_positions'] );

		$newPositions      = [];
		$newPositionKeys   = [];
		foreach ( $strings as $string ) {
			if ( count( $string->getNewPositions() ) > 0 && ! $string->hasId() ) {
				continue;
			}
			foreach ( $string->getNewPositions() as $position ) {
				$key = $string->getId()
					. ':'
					. $position->getKind()
					. ':'
					. strlen( $position->getPositionInPage() )
					. ':'
					. $position->getPositionInPage();
				if ( isset( $newPositionKeys[ $key ] ) ) {
					continue;
				}

				$newPositionKeys[ $key ] = true;
				$newPositions[]          = $position;
			}
		}
		$this->insertStringPositionsCommand->run( $newPositions );
	}
}
