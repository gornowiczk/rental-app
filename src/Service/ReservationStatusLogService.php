<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationStatusLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationStatusLogService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function log(
        Reservation $reservation,
        ?User $actor,
        ?string $oldStatus,
        string $newStatus,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $note = null
    ): void {
        $log = new ReservationStatusLog();
        $log->setReservation($reservation);
        $log->setActor($actor);
        $log->setOldStatus($this->cut($oldStatus, 32));
        $log->setNewStatus($this->cut($newStatus, 32));

        // kolumny w DB:
        // ip_address VARCHAR(64)
        // user_agent VARCHAR(255)
        // note VARCHAR(255)
        $log->setIpAddress($this->cut($ipAddress, 64));
        $log->setUserAgent($this->cut($userAgent, 255));
        $log->setNote($this->cut($note, 255));

        $this->em->persist($log);
        // flush robimy w kontrolerze/komendzie razem z resztą zmian
    }

    private function cut(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // substr działa OK dla ASCII; UA/notes zwykle są ASCII
        if (strlen($value) > $max) {
            return substr($value, 0, $max);
        }

        return $value;
    }
}
