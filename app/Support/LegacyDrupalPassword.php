<?php

namespace App\Support;

final class LegacyDrupalPassword
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function check(string $password, string $storedHash): bool
    {
        if (!str_starts_with($storedHash, '$S$') || strlen($storedHash) !== 55) {
            return false;
        }

        $countLog2 = strpos(self::ITOA64, $storedHash[3]);
        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return false;
        }

        $salt = substr($storedHash, 4, 8);
        $hash = md5($salt . $password, true);
        for ($iteration = 0, $count = 1 << $countLog2; $iteration < $count; $iteration++) {
            $hash = md5($hash . $password, true);
        }

        return hash_equals($storedHash, substr($storedHash, 0, 12) . self::encode64($hash, 16));
    }

    private static function encode64(string $input, int $count): string
    {
        $output = '';
        $index = 0;

        while ($index < $count) {
            $value = ord($input[$index++]);
            $output .= self::ITOA64[$value & 0x3f];

            if ($index < $count) {
                $value |= ord($input[$index]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3f];

            if ($index++ >= $count) {
                break;
            }
            $value |= ord($input[$index]) << 16;
            $output .= self::ITOA64[($value >> 12) & 0x3f];

            if ($index++ >= $count) {
                break;
            }
            $output .= self::ITOA64[($value >> 18) & 0x3f];
        }

        return $output;
    }
}
