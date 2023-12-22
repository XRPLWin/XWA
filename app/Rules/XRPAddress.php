<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class XRPAddress implements ValidationRule
{
  /**
   * Run the validation rule.
   *
   * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
   */
  public function validate(string $attribute, mixed $value, Closure $fail): void
  {
    $len = strlen($value);
    //dd($len > 25,$len < 35, ($len > 25 && $len < 35));
    if(!($len > 25 && $len < 35)) {
        $fail('The :attribute is not valid XRP Address format.');
    }
    //todo add base58 check here https://github.com/furqansiddiqui/base58check-php
  }
  
  /**
   * Run the validation rule.
   *
   * @param  string  $attribute
   * @param  mixed  $value
   * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
   * @return void
   */
  /*public function __invoke($attribute, $value, $fail)
  {
      $len = strlen($value);
      //dd($len > 25,$len < 35, ($len > 25 && $len < 35));
      if(!($len > 25 && $len < 35)) {
          $fail('The :attribute is not valid XRP Address format.');
      }

      //todo add base58 check here https://github.com/furqansiddiqui/base58check-php
  }*/
}
