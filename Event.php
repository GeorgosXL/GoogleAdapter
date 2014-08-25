<?php
/**
 * This file is part of the CalendArt package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

namespace CalendArt\Adapter\Google;

use Datetime,
    DateTimeZone,
    InvalidArgumentException;

use CalendArt\Event as BaseEvent,
    CalendArt\EventParticipation as BaseEventParticipation;

/**
 * Event model from a Google adapter point of view
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class Event extends BaseEvent
{
    /** @var string Event's id */
    private $id;

    /** @var Datetime when was this event created */
    private $createdAt;

    /** @var Datetime last updated date */
    private $updatedAt;

    /** @var string where this event is located at */
    public $location;

    public function __construct(Calendar $calendar, User $owner, Datetime $createdAt, Datetime $start, Datetime $end, $id, $name)
    {
        parent::__construct($calendar, $owner, $name, $start, $end);

        $this->id        = $id;
        $this->createdAt = $createdAt;
    }

    public function getId()
    {
        return $this->id;
    }

    /** @return Datetime */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /** @return $this */
    public function setUpdatedAt(Datetime $updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return boolean */
    public function wasUpdated()
    {
        return null !== $this->updatedAt;
    }

    /** @return Datetime */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /** @return $this */
    public function addParticipation(BaseEventParticipation $participation)
    {
        if (!$participation instanceof EventParticipation) {
            throw new InvalidArgumentException('Only a Google EventParticipation may be added as an attendee to a Google Event');
        }

        return parent::addParticipation($participation);
    }

    /**
     * Hydrate a new object from an array of data extracted from a returned json
     *
     * @param array $data JSON interpreted data returned by the event's api
     *
     * @throws InvalidArgumentException The data is not valid
     * @return static Event instance
     */
    public static function hydrate(Calendar $calendar, array $data)
    {
        if (!isset($data['id'], $data['summary'], $data['creator'], $data['created'], $data['start'], $data['end'])) {
            throw new InvalidArgumentException(sprintf('Missing at least one of the mandatory properties "id", "summary", "creator", "created", "start" or "end" ; got ["%s"]', implode('", "', array_keys($data))));
        }

        $owner = User::hydrate($data['creator']);

        $end      = new Datetime($data['end']);
        $start    = new Datetime($data['start']);
        $created  = new Datetime($data['created']);
        $userList = [$owner->getEmail() => $owner];

        $event = new static($calendar, $owner, $created, $start, $end, $data['id'], $data['summary']);

        if (isset($data['updated'])) {
            $event->setUpdatedAt(new Datetime($data['updated']));
        }

        if (isset($data['location'])) {
            $event->location = $data['location'];
        }

        if (isset($data['description'])) {
            $event->description = $data['description'];
        }

        $owner->addEvent($event);

        if (isset($data['attendees'])) {
            if (!is_array($data['attendees'])) {
                throw new InvalidArgumentException('The attendees data should be an array, %s given', is_object($data['attendees']) ? get_class($data['attendees']) : gettype($data['attendees']));
            }

            foreach ($data['attendees'] as $attendee) {
                $attendee = isset($userList[$attendee['email']]) ? $userList[$attendee['email']] : User::hydrate($attendee);

                $role = EventParticipation::ROLE_PARTICIPANT;

                if (isset($attendee['organizer']) && true === $attendee['organizer']) {
                    $role |= EventParticipation::ROLE_MANAGER;
                }

                $participant = new EventParticipation($event, $attendee, $role, EventParticipation::translateStatus($attendee['responseStatus']));

                $attendee->addEvent($event);
                $event->addParticipation($participation);
            }
        }
    }
}

