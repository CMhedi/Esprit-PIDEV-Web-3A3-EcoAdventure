<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Evenement;
use App\Entity\EventRating;
use App\Entity\UserApp;
use PHPUnit\Framework\TestCase;

class EventRatingTest extends TestCase
{
    public function testEventRatingDefaultValuesAndSetters(): void
    {
        $rating = new EventRating();

        // Default constructor sets createdAt
        $this->assertInstanceOf(\DateTimeInterface::class, $rating->getCreatedAt());

        $user = new UserApp();
        $rating->setUser($user);
        $this->assertSame($user, $rating->getUser());

        $evenement = new Evenement();
        $rating->setEvenement($evenement);
        $this->assertSame($evenement, $rating->getEvenement());

        $rating->setNote(5);
        $this->assertEquals(5, $rating->getNote());

        $rating->setCommentaire('Super event !');
        $this->assertEquals('Super event !', $rating->getCommentaire());

        $date = new \DateTime('+1 day');
        $rating->setCreatedAt($date);
        $this->assertEquals($date, $rating->getCreatedAt());
    }
}
