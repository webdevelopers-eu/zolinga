<?php
declare(strict_types=1);

namespace Zolinga\System\Events;

/**
 * This event "system:installer:patch:{$this->ext}" from origin Event::ORIGIN_INTERNAL
 * that is triggered when a patch needs to be installed.
 * 
 * If the event status is set to InstallScriptEvent::STATUS_OK, the patch is considered installed.
 * Otherwise, the patch is considered not installed and will be tried again later.
 * 
 * Prior to installing the patch, make sure the status is InstallScriptEvent::STATUS_UNDETERMINED.
 *   
 * When your handler installs the patch, set the status to InstallScriptEvent::STATUS_OK.
 * 
 * The event is stoppable.
 * 
 * @author Daniel Sevcik
 * @date 2024-02-08
 */
class InstallScriptEvent extends Event implements StoppableInterface
{
    use StoppableTrait;

    /**
     * The patch file to be installed.
     * @var string full path to the patch file
     */
    public readonly string $patchFile;

    /**
     * The extension of the patch file.
     * @var string the extension of the patch file. E.g. "php" or "sql"
     */
    public readonly string $ext;

    /**
     * Create a new InstallScriptEvent.
     * 
     * @param string $patchFile The patch file to be installed.
     * @return void
     */
    public function __construct(string $patchFile)
    {
        $this->ext = pathinfo($patchFile, PATHINFO_EXTENSION);
        $this->patchFile = $patchFile;

        parent::__construct("system:install:script:{$this->ext}", Event::ORIGIN_INTERNAL);
    }
}