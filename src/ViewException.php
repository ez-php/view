<?php

declare(strict_types=1);

namespace EzPhp\View;

/**
 * Class ViewException
 *
 * Base exception for all view-related errors: missing templates,
 * malformed section calls, and engine initialisation failures.
 *
 * @package EzPhp\View
 */
final class ViewException extends \RuntimeException
{
}
