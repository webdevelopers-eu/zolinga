<?php

namespace Zolinga\System\Events;

use Zolinga\System\Events\Event;
use Zolinga\System\Types\OriginEnum;
use Stringable, Exception;

/**
 * This event is used to check if the user has the rights to perform the action.
 * 
 * Your listener is supposed to check $event->unauthorized rights and see
 * if the user has them. If you cannot identify the rights or you have no
 * record of the rights, do nothing. Let other listeners to check the rights.
 * 
 * Example of the listener code:
 * 
 * foreach($event->unauthorized as $right) {
 *    if($user->hasRight($right)) {
 *      $event->authorize($right);
 *   }
 * }
 * 
 * @property-read array<string|Stringable> $authorized list of authorized rights
 * @property-read array<string|Stringable> $unauthorized list of unauthorized rights
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-26
 */
class AuthorizeEvent extends Event implements StoppableInterface
{
    use StoppableTrait;

    /**
     * The right that needs to be satisfied for the event to be authorized to be processed
     * by the target listener having the rights set.
     * 
     * Keys are preserved.
     *
     * @var array<string|Stringable> $unauthorized  
     */
    private array $unauthorized = [];

    /**
     * List of already authorized rights.
     * 
     * Keys are preserved.
     *
     * @var array<string|Stringable>
     */
    private array $authorized = [];

    /**
     * Constructor expects the right URI to be passed.
     *
     * @param array<string|Stringable> $rights URI of the right to check.
     */
    public function __construct(string $type, OriginEnum $origin, array $rights)
    {
        parent::__construct($type, $origin);
        array_walk($rights, fn ($right) => $this->addRight($right));
    }

    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'unauthorized':
                return $this->unauthorized;
            case 'authorized':
                return $this->authorized;
            default:
                return parent::__get($name);
        }
    }

    public function __set(string $name, mixed $value): void
    {
        throw new \Exception("Property $name is read-only. Use the \$event->authorize(\$right) method to authorize the right.");
    }


    /**
     * Add right to a list of unauthorized rights.
     *
     * @param string|Stringable $right
     * @return void
     */
    private function addRight(string|Stringable $right): void
    {
        $this->unauthorized[] = $right;
    }

    /**
     * Authorize the right.
     * 
     * Example:
     * 
     *  foreach($event->unauthorized as $right) {
     *     $event->authorize($right);
     *  }
     *  print_r($event->authorized);
     *
     * @param string|Stringable ...$rights
     * @return void
     */
    public function authorize(string|Stringable ...$rights): void
    {
        foreach ($this->unauthorized as $key => $uRight) {
            foreach ($rights as $right) {
                if (strval($uRight) == strval($right)) {
                    unset($this->unauthorized[$key]);
                    $this->authorized[$key] = $uRight;
                }
            }
        }

        if (!count($this->unauthorized)) { // that's all folks!
            $this->stopPropagation();
        }
    }

    /**
     * Check if the right is authorized.
     *
     * @param string|Stringable $right
     * @return boolean
     */
    public function isAuthorized(string|Stringable $right): bool
    {
        foreach ($this->authorized as $aRight) {
            if (strval($aRight) == strval($right)) {
                return true;
            }
        }
        return false;
    }
}
