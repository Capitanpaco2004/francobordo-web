<?php
#
# Portable PHP password hashing framework.
#
# Version 0.3 / genuine.
#
# Written by Solar Designer <solar at openwall.com> in 2004-2006 and placed in
# the public domain.  Revised in subsequent years, still public domain.
#
# There's absolutely no warranty.
#
# The homepage URL for this framework is:
#
#	http://www.openwall.com/phpass/
#
# Please be sure to update the Version line if you edit this file in any way.
# It is suggested that you leave the main version number intact, but indicate
# your project name (after the slash) and add your own revision information.
#
# Please do not change the "private" password hashing method implemented in
# here, thereby making your hashes incompatible.  However, if you must, please
# change the hash type identifier (the "$P$") to something different.
#
# Obviously, since this code is in the public domain, the above are not
# requirements (there can be none), but merely suggestions.
#
class PasswordHash {
	public $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	public $iteration_count_log2;
	public $random_state;

	function __construct($iteration_count_log2, public $portable_hashes)
	{
		if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31) {
            $iteration_count_log2 = 8;
        }
        $this->iteration_count_log2 = $iteration_count_log2;

		$this->random_state = microtime();
		if (function_exists('getmypid')) {
            $this->random_state .= getmypid();
        }
	}
	
	function generate_password( $length = 12, $special_chars = true, $extra_special_chars = false )
	{
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
		if ($extra_special_chars) {
            $chars .= '-_ []{}<>~`+=,.;:/?|';
        }
	 
		$password = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$password .= substr($chars, $this->random(0, strlen($chars) - 1), 1);
		}
	 
		return $password;
	}
	
	function RandomCompat_intval($number, $fail_open = false)
	{
		if (is_numeric($number)) {
			$number += 0;
		}
	 
		if (
			is_float($number)
			&&
			$number > ~PHP_INT_MAX
			&&
			$number < PHP_INT_MAX
		) {
			$number = (int) $number;
		}
	 
		if (is_int($number) || $fail_open) {
			return $number;
		}
	 
		throw new TypeError(
			'Expected an integer.'
		);
	}
	
	function random_int($min, $max)
	{
		/**
		 * Type and input logic checks
		 * 
		 * If you pass it a float in the range (~PHP_INT_MAX, PHP_INT_MAX)
		 * (non-inclusive), it will sanely cast it to an int. If you it's equal to
		 * ~PHP_INT_MAX or PHP_INT_MAX, we let it fail as not an integer. Floats 
		 * lose precision, so the <= and => operators might accidentally let a float
		 * through.
		 */
		 
		try {
			$min = $this->RandomCompat_intval($min);
		} catch (TypeError $ex) {
			throw new TypeError('random_int(): $min must be an integer', $ex->getCode(), $ex);
		}
	 
		try {
			$max = $this->RandomCompat_intval($max);
		} catch (TypeError) {
			throw new TypeError(
				'random_int(): $max must be an integer'
			);
		}
		 
		/**
		 * Now that we've verified our weak typing system has given us an integer,
		 * let's validate the logic then we can move forward with generating random
		 * integers along a given range.
		 */
		if ($min > $max) {
			throw new Error(
				'Minimum value must be less than or equal to the maximum value'
			);
		}
	 
		if ($max === $min) {
			return $min;
		}
	 
		/**
		 * Initialize variables to 0
		 * 
		 * We want to store:
		 * $bytes => the number of random bytes we need
		 * $mask => an integer bitmask (for use with the &) operator
		 *          so we can minimize the number of discards
		 */
		$attempts = $bits = $bytes = $mask = $valueShift = 0;
	 
		/**
		 * At this point, $range is a positive number greater than 0. It might
		 * overflow, however, if $max - $min > PHP_INT_MAX. PHP will cast it to
		 * a float and we will lose some precision.
		 */
		$range = $max - $min;
	 
		/**
		 * Test for integer overflow:
		 */
		if (!is_int($range)) {
	 
			/**
			 * Still safely calculate wider ranges.
			 * Provided by @CodesInChaos, @oittaa
			 * 
			 * @ref https://gist.github.com/CodesInChaos/03f9ea0b58e8b2b8d435
			 * 
			 * We use ~0 as a mask in this case because it generates all 1s
			 * 
			 * @ref https://eval.in/400356 (32-bit)
			 * @ref http://3v4l.org/XX9r5  (64-bit)
			 */
			$bytes = PHP_INT_SIZE;
			$mask = ~0;
	 
		} else {
	 
			/**
			 * $bits is effectively ceil(log($range, 2)) without dealing with 
			 * type juggling
			 */
			while ($range > 0) {
				if ($bits % 8 === 0) {
				   ++$bytes;
				}
				++$bits;
				$range >>= 1;
				$mask = $mask << 1 | 1;
			}
			$valueShift = $min;
		}
	 
		/**
		 * Now that we have our parameters set up, let's begin generating
		 * random integers until one falls between $min and $max
		 */
		do {
			/**
			 * The rejection probability is at most 0.5, so this corresponds
			 * to a failure probability of 2^-128 for a working RNG
			 */
			if ($attempts > 128) {
				throw new Exception(
					'random_int: RNG is broken - too many rejections'
				);
			}

			/**
			 * Let's grab the necessary number of random bytes
			 */
			$randomByteString = $this->get_random_bytes($bytes);
			if ($randomByteString === false) {
				throw new Exception(
					'Random number generator failure'
				);
			}

			/**
			 * Let's turn $randomByteString into an integer
			 * 
			 * This uses bitwise operators (<< and |) to build an integer
			 * out of the values extracted from ord()
			 * 
			 * Example: [9F] | [6D] | [32] | [0C] =>
			 *   159 + 27904 + 3276800 + 201326592 =>
			 *   204631455
			 */
			$val = 0;
			for ($i = 0; $i < $bytes; ++$i) {
				$val |= ord($randomByteString[$i]) << ($i * 8);
			}

			/**
			 * Apply mask
			 */
			$val &= $mask;
			$val += $valueShift;

			++$attempts;
			/**
			 * If $val overflows to a floating point number,
			 * ... or is larger than $max,
			 * ... or smaller than $min,
			 * then try again.
			 */
		} while (!is_int($val) || $val > $max || $val < $min);
	 
		return $val;
	}
	
	function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}
	
	function random( $min = 0, $max = 0 ) 
	{
		$rnd_value = 10;
	 
		// Some misconfigured 32bit environments (Entropy PHP, for example) truncate integers larger than PHP_INT_MAX to PHP_INT_MAX rather than overflowing them to floats.
		$max_random_number = 3000000000 === 2147483647 ? (float) "4294967295" : 4294967295; // 4294967295 = 0xffffffff
	 
		// We only handle Ints, floats are truncated to their integer value.
		$min = (int) $min;
		$max = (int) $max;
	 
		// Use PHP's CSPRNG, or a compatible method
		static $use_random_int_functionality = true;
		if ( $use_random_int_functionality ) {
			try {
				$_max = ( 0 != $max ) ? $max : $max_random_number;
				// wp_rand() can accept arguments in either order, PHP cannot.
				$_max = max( $min, $_max );
				$_min = min( $min, $_max );
				$val = $this->random_int( $_min, $_max );
				if ( false !== $val ) {
					return $this->absint( $val );
				} else {
					$use_random_int_functionality = false;
				}
			} catch ( Error|Exception ) {
				$use_random_int_functionality = false;
			}
		}
	 
		// Reset $rnd_value after 14 uses
		// 32(md5) + 40(sha1) + 40(sha1) / 8 = 14 random numbers from $rnd_value
		if ( strlen($rnd_value) < 8 ) {
			if (defined( 'WP_SETUP_CONFIG' )) {
                static $seed = '';
            } else {
                $seed = get_transient('random_seed');
            }
			$rnd_value = md5( uniqid(microtime() . mt_rand(), true ) . $seed );
			$rnd_value .= sha1($rnd_value);
			$rnd_value .= sha1($rnd_value . $seed);
			$seed = md5($seed . $rnd_value);
			if ( ! defined( 'WP_SETUP_CONFIG' ) && ! defined( 'WP_INSTALLING' ) ) {
				set_transient( 'random_seed', $seed );
			}
		}
	 
		// Take the first 8 digits for our value
		$value = substr($rnd_value, 0, 8);
	 
		$value = abs(hexdec($value));
	 
		// Reduce the value to be within the min - max range
		if ($max != 0) {
            $value = $min + ( $max - $min + 1 ) * $value / ( $max_random_number + 1 );
        }
	 
		return abs(intval($value));
	}

	function get_random_bytes($count)
	{
		$output = '';
        if (@is_readable('/dev/urandom') &&
		    ($fh = @fopen('/dev/urandom', 'rb'))) {
			if (function_exists('stream_set_read_buffer')) {
				stream_set_read_buffer($fh, 0);
			}
			$output = fread($fh, $count);
			fclose($fh);
		} elseif ( function_exists('openssl_random_pseudo_bytes') ) {
			$output = openssl_random_pseudo_bytes($count, $orpb_secure);

			if ( $orpb_secure != true ) {
				$output = '';
			}
		} elseif (defined('MCRYPT_DEV_URANDOM')) {
			$output = mcrypt_create_iv($count, MCRYPT_DEV_URANDOM);
		}

		if (strlen($output) < $count) {
			$output = '';
			for ($i = 0; $i < $count; $i += 16) {
				$this->random_state =
				    md5(microtime() . $this->random_state);
				$output .=
				    pack('H*', md5($this->random_state));
			}
			$output = substr($output, 0, $count);
		}

		return $output;
	}

	function encode64($input, $count)
	{
		$output = '';
		$i = 0;
		do {
			$value = ord($input[$i++]);
			$output .= $this->itoa64[$value & 0x3f];
			if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
			$output .= $this->itoa64[($value >> 6) & 0x3f];
			if ($i++ >= $count) {
                break;
            }
			if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
			$output .= $this->itoa64[($value >> 12) & 0x3f];
			if ($i++ >= $count) {
                break;
            }
			$output .= $this->itoa64[($value >> 18) & 0x3f];
		} while ($i < $count);

		return $output;
	}

	function gensalt_private($input)
	{
		$output = '$P$';
		$output .= $this->itoa64[min($this->iteration_count_log2 +
			((PHP_VERSION >= '5') ? 5 : 3), 30)];

		return $output . $this->encode64($input, 6);
	}

	function crypt_private($password, $setting)
	{
		$output = '*0';
		if (substr((string) $setting, 0, 2) === $output) {
            $output = '*1';
        }

		$id = substr((string) $setting, 0, 3);
		# We use "$P$", phpBB3 uses "$H$" for the same thing
		if ($id !== '$P$' && $id !== '$H$') {
            return $output;
        }

		$count_log2 = strpos((string) $this->itoa64, (string) $setting[3]);
		if ($count_log2 < 7 || $count_log2 > 30) {
            return $output;
        }

		$count = 1 << $count_log2;

		$salt = substr((string) $setting, 4, 8);
		if (strlen($salt) != 8) {
            return $output;
        }

		# We're kind of forced to use MD5 here since it's the only
		# cryptographic primitive available in all versions of PHP
		# currently in use.  To implement our own low-level crypto
		# in PHP would result in much worse performance and
		# consequently in lower iteration counts and hashes that are
		# quicker to crack (by non-PHP code).
		if (PHP_VERSION >= '5') {
			$hash = md5($salt . $password, TRUE);
			do {
				$hash = md5($hash . $password, TRUE);
			} while (--$count);
		} else {
			$hash = pack('H*', md5($salt . $password));
			do {
				$hash = pack('H*', md5($hash . $password));
			} while (--$count);
		}

		$output = substr((string) $setting, 0, 12);

		return $output . $this->encode64($hash, 16);
	}

	function gensalt_extended($input)
	{
		$count_log2 = min($this->iteration_count_log2 + 8, 24);
		# This should be odd to not reveal weak DES keys, and the
		# maximum valid value is (2**24 - 1) which is odd anyway.
		$count = (1 << $count_log2) - 1;

		$output = '_';
		$output .= $this->itoa64[$count & 0x3f];
		$output .= $this->itoa64[($count >> 6) & 0x3f];
		$output .= $this->itoa64[($count >> 12) & 0x3f];
		$output .= $this->itoa64[($count >> 18) & 0x3f];

		return $output . $this->encode64($input, 3);
	}

	function gensalt_blowfish($input)
	{
		# This one needs to use a different order of characters and a
		# different encoding scheme from the one in encode64() above.
		# We care because the last character in our encoded string will
		# only represent 2 bits.  While two known implementations of
		# bcrypt will happily accept and correct a salt string which
		# has the 4 unused bits set to non-zero, we do not want to take
		# chances and we also do not want to waste an additional byte
		# of entropy.
		$itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

		$output = '$2a$';
		$output .= chr(ord('0') + $this->iteration_count_log2 / 10);
		$output .= chr(ord('0') + $this->iteration_count_log2 % 10);
		$output .= '$';

		$i = 0;
		do {
			$c1 = ord($input[$i++]);
			$output .= $itoa64[$c1 >> 2];
			$c1 = ($c1 & 0x03) << 4;
			if ($i >= 16) {
				$output .= $itoa64[$c1];
				break;
			}

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 4;
			$output .= $itoa64[$c1];
			$c1 = ($c2 & 0x0f) << 2;

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 6;
			$output .= $itoa64[$c1];
			$output .= $itoa64[$c2 & 0x3f];
		} while (1);

		return $output;
	}

	function HashPassword($password)
	{
		$random = '';

		if (!$this->portable_hashes) {
			$random = $this->get_random_bytes(16);
			$hash =
			    crypt((string) $password, (string) $this->gensalt_blowfish($random));
			if (strlen($hash) == 60) {
                return $hash;
            }
		}

		if (!$this->portable_hashes) {
			if (strlen((string) $random) < 3) {
                $random = $this->get_random_bytes(3);
            }
			$hash =
			    crypt((string) $password, (string) $this->gensalt_extended($random));
			if (strlen($hash) == 20) {
                return $hash;
            }
		}

		if (strlen((string) $random) < 6) {
            $random = $this->get_random_bytes(6);
        }
		$hash =
		    $this->crypt_private($password,
		    $this->gensalt_private($random));
		if (strlen((string) $hash) == 34) {
            return $hash;
        }

		# Returning '*' on error is safe here, but would _not_ be safe
		# in a crypt(3)-like function used _both_ for generating new
		# hashes and for validating passwords against existing hashes.
		return '*';
	}

	function CheckPassword($password, $stored_hash)
	{
		$hash = $this->crypt_private($password, $stored_hash);
		if ($hash[0] == '*') {
            $hash = crypt((string) $password, (string) $stored_hash);
        }

		return $hash == $stored_hash;
	}
}

?>
