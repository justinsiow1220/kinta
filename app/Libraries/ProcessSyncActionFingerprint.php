<?php

namespace App\Libraries;

use JsonException;
use RuntimeException;

/** Builds stable identities for individual offline sync actions. */
final class ProcessSyncActionFingerprint
{
    /** @throws RuntimeException */
    public static function generate(string $actionType, string $localProcessId, array $fields): string
    {
        $identity = self::sortAssociativeKeys([
            'action_type'      => $actionType,
            'local_process_id' => $localProcessId,
            'fields'           => $fields,
        ]);

        try {
            $canonical = json_encode(
                $identity,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not canonicalize the offline sync action.', 0, $exception);
        }

        return 'sha256-v1:' . hash('sha256', $canonical);
    }

    private static function sortAssociativeKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortAssociativeKeys($item);
            }
        }

        return $value;
    }
}
