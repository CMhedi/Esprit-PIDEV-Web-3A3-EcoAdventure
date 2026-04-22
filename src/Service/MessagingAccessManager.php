<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\UserApp;
use App\Repository\BlockedMessagingUserRepository;

class MessagingAccessManager
{
    /**
     * @var int[]|null
     */
    private ?array $blockedUserIds = null;

    public function __construct(
        private readonly BlockedMessagingUserRepository $blockedMessagingUserRepository
    ) {
    }

    /**
     * @return int[]
     */
    public function getBlockedUserIds(): array
    {
        if ($this->blockedUserIds === null) {
            $blockedIds = $this->blockedMessagingUserRepository->findActiveBlockedUserIds();
            $blockedIds = array_values(array_filter(array_map('intval', $blockedIds), static fn (int $id): bool => $id > 0));
            sort($blockedIds);
            $this->blockedUserIds = $blockedIds;
        }

        return $this->blockedUserIds;
    }

    public function isUserCallBlocked(?UserApp $user): bool
    {
        return $user instanceof UserApp && $this->isUserIdCallBlocked($user->getId_user());
    }

    public function isUserIdCallBlocked(?int $userId): bool
    {
        return $userId !== null && in_array($userId, $this->getBlockedUserIds(), true);
    }

    public function isConversationCallBlocked(?Conversation $conversation): bool
    {
        if (!$conversation instanceof Conversation) {
            return false;
        }

        foreach ($conversation->getParticipants() as $participant) {
            if ($this->isUserCallBlocked($participant)) {
                return true;
            }
        }

        return $conversation->getCreateur() instanceof UserApp
            && $this->isUserCallBlocked($conversation->getCreateur());
    }

    /**
     * @param iterable<UserApp> $users
     */
    public function containsCallBlockedUser(iterable $users): bool
    {
        foreach ($users as $user) {
            if ($user instanceof UserApp && $this->isUserCallBlocked($user)) {
                return true;
            }
        }

        return false;
    }
}
