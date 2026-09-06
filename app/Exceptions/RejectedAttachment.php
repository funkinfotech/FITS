<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see \App\Support\AttachmentPipeline} when an upload fails a
 * security or format check. The message is safe to show to the uploader.
 */
class RejectedAttachment extends RuntimeException
{
}
