<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A parse failure whose cause is already known well enough to explain to the user.
 *
 * ProcessImportJob runs in a queue worker, which has no HTTP session — so __()
 * inside the job resolves against the default locale, not the importer's. This
 * exception therefore carries a locale-independent *code*, and
 * AssetImportController::status() turns it into prose in the user's own request,
 * where the locale is actually known.
 *
 * Codes double as translation keys: 'assets.<code>' is the message and
 * 'assets.<code>_hint' the "possible solutions" guidance.
 */
class ImportFailure extends RuntimeException
{
    public const UNREADABLE = 'import_error_unreadable';

    public const NO_HEADER = 'import_error_no_header';

    public const FILE_MISSING = 'import_error_file_missing';

    public const NO_PROPERTY = 'import_error_no_property';

    public const INTERRUPTED = 'import_error_interrupted';

    public const GENERIC = 'import_error_generic';

    public function __construct(
        public readonly string $code_,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $code_);
    }

    public static function unreadable(string $detail = ''): self
    {
        return new self(self::UNREADABLE, $detail);
    }

    public static function noHeader(string $detail = ''): self
    {
        return new self(self::NO_HEADER, $detail);
    }

    public static function fileMissing(string $detail = ''): self
    {
        return new self(self::FILE_MISSING, $detail);
    }

    public static function noProperty(string $detail = ''): self
    {
        return new self(self::NO_PROPERTY, $detail);
    }
}
