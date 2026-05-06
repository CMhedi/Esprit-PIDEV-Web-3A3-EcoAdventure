<?php

namespace App\Notification;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class GoalStatusEmailNotification extends Notification implements EmailNotificationInterface
{
    public function __construct(
        string $subject,
        private readonly string $htmlContent,
        string $importance = self::IMPORTANCE_HIGH,
        string $emoji = ''
    ) {
        parent::__construct($subject, ['email']);
        $this->importance($importance);

        if ($emoji !== '') {
            $this->emoji($emoji);
        }
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        $email = (new Email())
            ->from(new Address('sabribenfalah03@gmail.com', 'EcoAdventure App'))
            ->to($recipient->getEmail())
            ->subject($this->getSubject())
            ->html($this->htmlContent);

        $message = new EmailMessage($email);

        if ($transport !== null) {
            $message->transport($transport);
        }

        return $message;
    }
}
