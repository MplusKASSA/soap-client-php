<?php

namespace MplusKASSA\SOAP;

/**
 * Helper methods
 */
class Helper {

    /**
     * getQuantityAndDecimalPlaces
     * 
     * @param mixed $input         Input string with decimal/float with . or ,
     *
     * @return array with quantity and decimal places
     */
    public static function getQuantityAndDecimalPlaces($input): array {
        if (is_string($input)) {
            $input = str_replace(',', '.', $input);
        }
        $input = round(floatval($input), 6);
        $origInput = $input;
        $decimalPlaces = -1;
        do {
            $intPart = (int) $input;
            $input -= $intPart;
            $input *= 10;
            $input = round($input, 6);
            $decimalPlaces++;
        }
        while ($input >= 0.0000001);
        $quantity = (int) ($origInput * pow(10, $decimalPlaces));
        return array($quantity, $decimalPlaces);
    }

    /**
     * fromQuantityAndDecimalPlaces
     * 
     * @param mixed $quantity         The quantity
     * @param int $decimalPlaces    Optional amount of decimal places
     *
     * @return float
     */
    public static function fromQuantityAndDecimalPlaces($quantity, $decimalPlaces): float {
        $decimalPlaces = intval($decimalPlaces);
        $output = (float) intval($quantity);
        $decimalPlaces = (float) $decimalPlaces;
        $output = $output / pow(10, $decimalPlaces);
        return $output;
    }

}
