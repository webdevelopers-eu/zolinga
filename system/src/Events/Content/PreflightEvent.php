<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Content;

use Zolinga\System\Types\{ContentMimeTypesEnum, StatusEnum, SeverityEnum};
use Zolinga\System\Events\{ContentEvent, StoppableInterface, StoppableTrait};

/**
 * Event that is supposed to be triggered before the content is generated.
 * It allows handlers to perform preflight checks and potentially prevent the content generation
 * or determine the content type (text, html, json, etc.) before the content is generated.
 * 
 * Based on the prefilight check the appropriate Event object type will be created and dispatched. 
 * 
 * For example, if the preflight check determines that the content type is HTML, then 
 * an HtmlContentEvent will be created and dispatched. If the preflight check determines that the content type is JSON,
 * then a JsonContentEvent will be created and dispatched.
 * 
 * If the preflight check status is not OK, then the content generation will be prevented and 
 * the appropriate error response will be sent. 
 */
class PreflightEvent extends ContentEvent
{

    /**
     * If preflight event is set to error status, this property can be set to provide an error message to be sent in the response
     * to the client. 
     *
     * @var string
     */
    public ?string $errorResponse = null;

    /**
     * This determines the type of output.
     *
     * @var ContentMimeTypesEnum expected format: type/subtype, e.g. text/html, application/json, image/png
     */
    public ContentMimeTypesEnum $mimeType = ContentMimeTypesEnum::TEXT_HTML;

    /**
     * @param mixed $path The URL path to the content.
     * @return void
     */
    public function __construct(mixed $path) {
        parent::__construct("system:content:preflight", self::ORIGIN_REMOTE, $path);
    }

    public function getContent(): ?string {
        return $this->errorResponse;
    }
    
    public function getOutput(): string {
        if ($this->status === StatusEnum::OK) {
            return '';
        }

        $publicErrorMessage = $this->errorResponse 
            ?: "{$this->statusName}: An error occurred while processing the request.";

        switch ($this->mimeType) {
            case ContentMimeTypesEnum::TEXT_HTML:
                return "<html><body><h1>Error</h1><p>{$publicErrorMessage}</p></body></html>";
            case ContentMimeTypesEnum::APPLICATION_JSON:
                return json_encode(['error' => $publicErrorMessage], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            case ContentMimeTypesEnum::TEXT_PLAIN:
                return "Error: {$publicErrorMessage}";
            default:
                return $publicErrorMessage; // Fallback to plain text for unknown MIME types
        }
    }
}