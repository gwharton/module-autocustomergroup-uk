<?php
namespace Gw\AutoCustomerGroupUk\SDK\Requests;

use Saloon\Http\Request;

abstract class BaseRequest extends Request
{
    protected function toArray(mixed $dtoObject)
    {
        return $this->array_filter_recursive((array)$dtoObject);
    }

    private function array_filter_recursive($input)
    {
        foreach ($input as $key => &$value) {
            if (is_array($value) || is_object($value)) {
                $value = $this->array_filter_recursive($value);
                if (empty($value)) {
                    $input = $this->unset($input, $key);
                }
            } elseif ($value === null) {
                $input = $this->unset($input, $key);
            }
        }
        return $input;
    }

    private function unset($item, $key)
    {
        if (is_array($item)) {
            unset($item[$key]);
        }
        if (is_object($item)) {
            unset($item->$key);
        }
        return $item;
    }
}
