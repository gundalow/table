<?php
/*
 * Password Hashing With PBKDF2 (http://crackstation.net/hashing-security.htm).
 * Copyright (c) 2013, Taylor Hornby
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, 
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation 
 * and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" 
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE 
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE 
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR 
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF 
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS 
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN 
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE.
 */
// Adapted by Float Design LTD

define("PBKDF2_HASH_ALGORITHM", "sha256");
define("PBKDF2_ITERATIONS", 1000);
define("PBKDF2_SALT_BYTE_SIZE", 24);
define("PBKDF2_HASH_BYTE_SIZE", 24);

define("HASH_SECTIONS", 4);
define("HASH_ALGORITHM_INDEX", 0);
define("HASH_ITERATION_INDEX", 1);
define("HASH_SALT_INDEX", 2);
define("HASH_PBKDF2_INDEX", 3);

abstract class pbkdf2 {
  static public function create_hash($password) {
    // format: algorithm:iterations:salt:hash
    $salt = base64_encode(mcrypt_create_iv(PBKDF2_SALT_BYTE_SIZE, MCRYPT_DEV_URANDOM));
    return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" .  $salt . ":" .
        base64_encode(self::do_pbkdf2(
            PBKDF2_HASH_ALGORITHM,
            $password,
            $salt,
            PBKDF2_ITERATIONS,
            PBKDF2_HASH_BYTE_SIZE,
            true
        ));
  }
  public static function validate_password($password, $correct_hash) {
    $params = explode(":", $correct_hash);
    if(count($params) < HASH_SECTIONS) return false;

    $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);
    
    return self::slow_equals(
      $pbkdf2,
      self::do_pbkdf2(
        $params[HASH_ALGORITHM_INDEX],
        $password,
        $params[HASH_SALT_INDEX],
        (int)$params[HASH_ITERATION_INDEX],
        strlen($pbkdf2),
        true
      )
    );
  }
  public static function slow_equals($a, $b) {
    $diff = strlen($a) ^ strlen($b);
    for($i = 0; $i < strlen($a) && $i < strlen($b); $i++) {
      $diff |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $diff === 0;
  }
  public static function do_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false) {
    $algorithm = strtolower($algorithm);
    if(!in_array($algorithm, hash_algos(), true)) trigger_error('PBKDF2 ERROR: Invalid hash algorithm.', E_USER_ERROR);
    if($count <= 0 || $key_length <= 0) trigger_error('PBKDF2 ERROR: Invalid parameters.', E_USER_ERROR);

    if (function_exists("hash_pbkdf2")) {
      // The output length is in NIBBLES (4-bits) if $raw_output is false!
      if (!$raw_output) {
        $key_length = $key_length * 2;
      }
      return hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output);
    }

    $hash_length = strlen(hash($algorithm, "", true));
    $block_count = ceil($key_length / $hash_length);

    $output = "";
    for($i = 1; $i <= $block_count; $i++) {
      // $i encoded as 4 bytes, big endian.
      $last = $salt . pack("N", $i);
      // first iteration
      $last = $xorsum = hash_hmac($algorithm, $last, $password, true);
      // perform the other $count - 1 iterations
      for ($j = 1; $j < $count; $j++) {
        $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
      }
      $output .= $xorsum;
    }

    if ($raw_output) return substr($output, 0, $key_length);
    else return bin2hex(substr($output, 0, $key_length));
  }
}
?>