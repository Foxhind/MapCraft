<?php
/***************************************************************************
*
* Author : Eric Sizemore ( www.secondversion.com & www.phpsociety.com )
* Package : Random Word
* Version : 1.0.1
* Copyright: (C) 2006 - 2007 Eric Sizemore
* Site	: www.secondversion.com & www.phpsociety.com
* Email	: esizemore05@gmail.com
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
***************************************************************************/

// Slightly inspired by class randomWord by kumar mcmillan
class Rand_Word {
	var $vowels = array('a','e','i','o','u');
	var $consonants = array('b','c','d','f','g','h','j','k','l','m','n','p','r','s','t','v','w','z','ch','qu','th','xy');
	var $word = '';

	/**
	* Constructor.
	*
	* @param integer Length of the word
	* @param boolean Return the word lowercase?
	* @param boolean Reutrn the word with the first letter capitalized?
	* @param boolean Return the word uppercase?
	* @return string
	*/
	function rand_word($length = 5, $lower_case = true, $ucfirst = false, $upper_case = false)
	{
		$done = false;
		$const_or_vowel = 1;

		while (!$done)
		{
			switch ($const_or_vowel)
			{
				case 1:
					$this->word .= $this->consonants[array_rand($this->consonants)];
					$const_or_vowel = 2;
					break;
				case 2:
					$this->word .= $this->vowels[array_rand($this->vowels)];
					$const_or_vowel = 1;
					break;
			}

			if (strlen($this->word) >= $length)
			{
				$done = true;
			}
		}

		$this->word = substr($this->word, 0, $length);

		if ($lower_case)
		{
			$this->word = strtolower($this->word);
		}
		else if ($ucfirst)
		{
			$this->word = ucfirst(strtolower($this->word));
		}
		else if ($upper_case)
		{
			$this->word = strtoupper($this->word);
		}
		return $this->word;
	}
}


/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

session_start();

$preconfig_db_unnecessary = true;

/*
 * File: CaptchaSecurityImages.php
 * Author: Simon Jarvis
 * Copyright: 2006 Simon Jarvis
 * Date: 03/08/06
 * Updated: 07/02/07
 * Requirements: PHP 4/5 with GD and FreeType libraries
 * Link: http://www.white-hat-web-design.co.uk/articles/php-captcha.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details:
 * http://www.gnu.org/licenses/gpl.html
 *
 */

class CaptchaSecurityImages {

	function CaptchaSecurityImages($width='120',$height='40',$characters='6',$font) {
		global $font,$font_ballback;

		$randword_class = new Rand_Word;

		$code = $randword_class->rand_word($characters);

		/* font size will be 75% of the image height */
		$font_size = $height * 0.85;
		$image = @imagecreate($width, $height) or die('Cannot initialize new GD image stream');
		/* set the colours */
		$background_color = imagecolorallocate($image, 255, 255, 255);
		$text_color = imagecolorallocate($image, 105, 100, 90);
		$noise_color = imagecolorallocate($image, 173, 164, 150);
		/* generate random dots in background */
		if (@imagettfbbox($font_size, 0, $font, $code)) {
			$ttf_supported = true;
			for( $i=0; $i<($width*$height)/3; $i++ ) {
				imagefilledellipse($image, mt_rand(0,$width), mt_rand(0,$height), 1, 1, $noise_color);
			}
		} else {
			$ttf_supported = false;
			for( $i=0; $i<($width*$height)/12; $i++ ) {
				imagefilledellipse($image, mt_rand(0,$width), mt_rand(0,$height), 1, 1, $noise_color);
			}
		}
		/* generate random lines in background */
		if ($ttf_supported) {
			for( $i=0; $i<($width*$height)/150; $i++ ) {
				imageline($image, mt_rand(0,$width), mt_rand(0,$height), mt_rand(0,$width), mt_rand(0,$height), $noise_color);
			}
		}
		if ($ttf_supported) {
			$textbox = imagettfbbox($font_size, 0, $font, $code);
			$x = ($width - $textbox[4])/2;
			$y = ($height - $textbox[5])/2;
			imagettftext($image, $font_size, 0, $x, $y, $text_color, $font, $code);
		} else {
			$x = 4;
			$y = 5;
			imagestring($image, $font_ballback, $x, $y, $code, $text_color);
		}
		/* create textbox and add text*/
		$textbox = imagettfbbox($font_size, 0, $font, $code) or die('Error in imagettfbbox function');
		$x = ($width - $textbox[4])/2;
		$y = ($height - $textbox[5])/2;
		imagettftext($image, $font_size, 0, $x, $y, $text_color, $font, $code) or die('Error in imagettftext function');
		/*output captcha image to browser */
		header('Content-Type: image/jpeg');
		imagejpeg($image);
		imagedestroy($image);
		$_SESSION['security_code'] = $code;
	}

}

$width = 90;
$height = 20;
$characters = 6;

$font = 'fonts/monofont.ttf';
$font_fallback = imageloadfont('fonts/captchafont.gdf');

$captcha = new CaptchaSecurityImages($width,$height,$characters,$font);

?>