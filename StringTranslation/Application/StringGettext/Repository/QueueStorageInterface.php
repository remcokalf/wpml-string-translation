<?php

namespace WPML\StringTranslation\Application\StringGettext\Repository;

interface QueueStorageInterface {
	public function getPendingStringDomainNames(): array;
	public function getProcessedStringDomainNames(): array;
	public function getProcessedStringsByDomain( string $domain ): array;
	public function getPendingStringsByDomain( string $domain ): array;
	public function claimPendingStringsByDomain( string $domain ): array;
	public function getPendingStringsClaimProgress( string $claimId ): array;
	public function savePendingStringsClaimProgress( string $claimId, array $progress ): bool;
	public function acknowledgePendingStringsClaim( string $claimId ): bool;
	public function releasePendingStringsClaim( string $claimId );
}
