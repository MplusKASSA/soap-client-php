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
     * @param int   $decimalPlaces    Optional amount of decimal places
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

    /**
     * decimalifyField
     * 
     * Search for a field with its decimal places an replace it for a decimal field
     * 
     * @param mixed     $data                           An array or object to decimalify a field
     * @param string    $sourceFieldName                The source field name which contains an integer value
     * @param string    $sourceDecimalPlacesFieldName   The source decimal places field name which contains an integer with the amount of decimal places
     * @param string    $targetFieldName                Optional target field name where to place the decimal value. If ommited, the decimal value is placed in the $sourceFieldName
     *
     */
    public static function decimalifyField(&$data, string $sourceFieldName, string $sourceDecimalPlacesFieldName, ?string $targetFieldName = null): void {
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                if (is_array($data)) {
                    self::decimalifyField($data[$key], $sourceFieldName, $sourceDecimalPlacesFieldName, $targetFieldName);
                } else {
                    self::decimalifyField($data->$key, $sourceFieldName, $sourceDecimalPlacesFieldName, $targetFieldName);
                }
            } else {
                if ($key == $sourceFieldName) {
                    if (is_null($targetFieldName)) {
                        $targetFieldName = $sourceFieldName;
                    }
                    $decimalPlaces = 0;
                    if (is_array($data)) {
                        unset($data[$sourceFieldName]);
                        if (array_key_exists($sourceDecimalPlacesFieldName, $data)) {
                            $decimalPlaces = $data[$sourceDecimalPlacesFieldName];
                            unset($data[$sourceDecimalPlacesFieldName]);
                        }
                        $data[$targetFieldName] = self::fromQuantityAndDecimalPlaces($value, $decimalPlaces);
                    } else {
                        unset($data->$sourceFieldName);
                        if (property_exists($data, $sourceDecimalPlacesFieldName)) {
                            $decimalPlaces = $data->$sourceDecimalPlacesFieldName;
                            unset($data->$sourceDecimalPlacesFieldName);
                        }
                        $data->$targetFieldName = self::fromQuantityAndDecimalPlaces($value, $decimalPlaces);
                    }
                }
            }
        }
    }

    /**
     * undecimalifyField
     * 
     * Search for a decimal field and replace it for a target field containing an integer and a target decimal places field containing the decimal places.
     * 
     * @param mixed     $data                           An array or object to undecimalify a field
     * @param string    $sourceFieldName                The source field name which contains a decimal value
     * @param string    $targetDecimalPlacesFieldName   The target decimal places field name which contains an integer with the amount of decimal places
     * @param string    $targetFieldName                Optional target field name where to place the integer value. If ommited, the integer value is placed in the $sourceFieldName
     *
     */
    public static function undecimalifyField(&$data, string $sourceFieldName, string $targetDecimalPlacesFieldName, ?string $targetFieldName = null): void {
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                if (is_array($data)) {
                    self::undecimalifyField($data[$key], $sourceFieldName, $targetDecimalPlacesFieldName, $targetFieldName);
                } else {
                    self::undecimalifyField($data->$key, $sourceFieldName, $targetDecimalPlacesFieldName, $targetFieldName);
                }
            } else {
                if ($key == $sourceFieldName) {
                    if (is_null($targetFieldName)) {
                        $targetFieldName = $sourceFieldName;
                    }
                    if (is_array($data)) {
                        unset($data[$sourceFieldName]);
                        list($data[$targetFieldName], $data[$targetDecimalPlacesFieldName]) = self::getQuantityAndDecimalPlaces($value);
                    } else {
                        unset($data->$sourceFieldName);
                        list($data->$targetFieldName, $data->$targetDecimalPlacesFieldName) = self::getQuantityAndDecimalPlaces($value);
                    }
                }
            }
        }
    }

}
