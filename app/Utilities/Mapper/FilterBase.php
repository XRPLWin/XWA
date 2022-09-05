<?php

namespace App\Utilities\Mapper;

use App\Utilities\Mapper\FilterInterface;

abstract class FilterBase implements FilterInterface {

  /**
   * Return eq or lte depending of parameters
   * @param string $existingE eq|lte
   * @param string $newE eq|lte
   * @return string eq|lte
   */
  protected function calcEqualizer(string $existingE, string $newE): string
  {
    if($existingE == 'lte')
      return 'lte';
      
    return $newE;
  }
}
