<?php

/**
 *
 * Adobe Photoshop Pattern generator by Xizzzy (http://xizzzy.ru/)
 * Version 0.2
 *
 * Changelog:
 * xx.xx.2010 v0.1
 * Indexed Colors
 *
 * 29.11.2010 v0.2
 * Grayscale, RGB and RGBA added
 *
 * 26.02.2020
 * RLE
 *
 */

define('PHOTOSHOP_PATTERN_GRAY', 1);
define('PHOTOSHOP_PATTERN_INDEX', 2);
define('PHOTOSHOP_PATTERN_RGB', 3);

class PhotoshopPattern
{

	/**
	 * Raw data of pattern file
	 */
	private $rawdata = '';

	/**
	 * Pattern file header
	 */
	private $header = "\x38\x42\x50\x54"; // 8BPT

	/**
	 * Default version
	 */
	private $version = 1;

	/**
	 * Array for patterns
	 */
	private $pattern = array();

	/**
	 * Flag for status
	 */
	private $changed = false;

	/**
	 * Constructor
	 */
	public function __construct($version = false)
	{
		if ($version)
			$this->version = $version;
	}

	/**
	 * CreatePattern (width, height, patternName, type)
	 * Return patternID
	 */
	public function CreatePattern($width, $height, $name, $type)
	{
		$id = count($this->pattern);

		$this->pattern[$id]['pallete'] = array();
		$this->pattern[$id]['channel'] = array();
		$this->pattern[$id]['alpha'] = false;
		$this->pattern[$id]['version'] = 1;
		$this->pattern[$id]['type'] = $type;
		$this->pattern[$id]['height'] = $height;
		$this->pattern[$id]['width'] = $width;
		$this->pattern[$id]['top'] = 0;
		$this->pattern[$id]['left'] = 0;
		$this->pattern[$id]['bottom'] = $height;
		$this->pattern[$id]['right'] = $width;
		$this->pattern[$id]['depth'] = 24;
		$this->SetPatternName($id, $name);

		switch ($type) {
			case 1: // Gray
				$this->CreateChannel($id, $width, $height);
				break;

			case 2: // Indexed
				$this->CreateChannel($id, $width, $height);
				break;

			case 3: // RGB
				$this->CreateChannel($id, $width, $height); // Red
				$this->CreateChannel($id, $width, $height); // Green
				$this->CreateChannel($id, $width, $height); // Blue
				$this->CreateChannel($id, $width, $height); // Alpha
				break;

			default:
				return false;
				break;
		}

		$this->changed = true;
		return $id;
	}

	/**
	 * CreateChannel (patternID, Width, Height)
	 * Return channel id
	 */
	private function CreateChannel($id, $width, $height)
	{
		$channel_id = count($this->pattern[$id]['channel']);
		$channel_data_size = $width * $height;

		$this->pattern[$id]['channel'][$channel_id]['version'] = 1;
		$this->pattern[$id]['channel'][$channel_id]['size'] = 23 + $channel_data_size;
		$this->pattern[$id]['channel'][$channel_id]['top'] = 0;
		$this->pattern[$id]['channel'][$channel_id]['left'] = 0;
		$this->pattern[$id]['channel'][$channel_id]['bottom'] = $height;
		$this->pattern[$id]['channel'][$channel_id]['right'] = $width;
		$this->pattern[$id]['channel'][$channel_id]['depth'] = 8; // Bit per channel
		$this->pattern[$id]['channel'][$channel_id]['compression'] = 0; // RLE compression
		$this->pattern[$id]['channel'][$channel_id]['data'] = pack('x' . $channel_data_size);

		return $channel_id;
	}

	/**
	 * ColorAllocate (patternID, RGB[, transparance percent])
	 * Return colorID
	 */
	public function ColorAllocate($id, $color, $transparance = 0)
	{
		if ($color_id = in_array($color, $this->pattern[$id]['pallete']))
			return $color_id;

		$color_id = count($this->pattern[$id]['pallete']);

		switch ($this->pattern[$id]['type']) {
			case 1: // Gray
				if (preg_match('/^[a-f0-9]{2}$/i', $color)) {
					$this->pattern[$id]['pallete'][] = $color;
				} else return false;
				break;

			case 2: // Indexed
				if (preg_match('/^[a-f0-9]{6}$/i', $color)) {
					if ($color_id < 257) {
						$this->pattern[$id]['pallete'][] = $color;
					} else return false;
				} else return false;
				break;

			case 3: // RGB or RGBA
				if (preg_match('/^[a-f0-9]{6}$/i', $color)) {
					if ($transparance < 1) {
						$this->pattern[$id]['pallete'][] = $color . '00';
					} else if ($transparance > 0 && $transparance < 101) {
						$this->pattern[$id]['pallete'][] = $color . str_pad(dechex(round($transparance * 255 / 100)), 2, '0', STR_PAD_LEFT);
					} else {
						$this->pattern[$id]['pallete'][] = $color . 'ff';
					}
				} else if (preg_match('/^[a-f0-9]{8}$/i', $color)) {
					$this->pattern[$id]['pallete'][] = $color;
				} else return false;
				break;

			default:
				return false;
				break;
		}

		$this->changed = true;
		return $color_id;
	}

	/**
	 * DrawRect (patternID, startPositionX, startPositionY, width, height, colorID)
	 */
	public function DrawRect($id, $x1, $y1, $width, $height, $color)
	{
		$x2 = $x1 + $width;
		$y2 = $y1 + $height;

		if ($x1 > $this->pattern[$id]['width']
			|| $x2 > $this->pattern[$id]['width']
			|| $y1 > $this->pattern[$id]['height']
			|| $y2 > $this->pattern[$id]['height']
		)
			return false;

		switch ($this->pattern[$id]['type']) {
			case 1: // Gray
				$row = str_pad('', $width, pack('H*', $this->pattern[$id]['pallete'][$color]));
				for ($y = $y1; $y < $y2; $y++) {
					$this->pattern[$id]['channel'][0]['data'] = substr_replace(
						$this->pattern[$id]['channel'][0]['data'],
						$row,
						$y * $this->pattern[$id]['width'] + $x1,
						$width
					);
				}
				break;

			case 2: // Indexed
				$row = str_pad('', $width, pack('C', $color));
				for ($y = $y1; $y < $y2; $y++) {
					$this->pattern[$id]['channel'][0]['data'] = substr_replace(
						$this->pattern[$id]['channel'][0]['data'],
						$row,
						$y * $this->pattern[$id]['width'] + $x1,
						$width
					);
				}
				break;

			case 3: // RGB
				$colors = str_split($this->pattern[$id]['pallete'][$color], 2);
				foreach ($colors as $channel => $value) {
					$row = str_pad('', $width, pack('H*', $value));
					for ($y = $y1; $y < $y2; $y++) {
						$this->pattern[$id]['channel'][$channel]['data'] = substr_replace(
							$this->pattern[$id]['channel'][$channel]['data'],
							$row,
							$y * $this->pattern[$id]['width'] + $x1,
							$width
						);
					}
				}
				break;

			default:
				return false;
				break;
		}

		$this->changed = true;
		return true;
	}

	/**
	 * DrawPixel (patternID, startPositionX, startPositionY, colorID)
	 */
	public function DrawPixel($id, $x, $y, $color)
	{
		switch ($this->pattern[$id]['type']) {
			case 1: // Gray
				$this->pattern[$id]['channel'][0]['data'][$y * $this->pattern[$id]['width'] + $x] = pack('H*', $this->pattern[$id]['pallete'][$color]);
				break;

			case 2: // Indexed
				$this->pattern[$id]['channel'][0]['data'][$y * $this->pattern[$id]['width'] + $x] = pack('C', $color);
				break;

			case 3: // RGB
				preg_match_all('/[a-f0-9]{2}/i', $this->pattern[$id]['pallete'][$color], $matches);
				foreach ($matches[0] as $channel => $value) {
					$this->pattern[$id]['channel'][$channel]['data'][$y * $this->pattern[$id]['width'] + $x] = pack('H*', $value);
				}
				break;

			default:
				return false;
				break;
		}

		$this->changed = true;
		return true;
	}

	/**
	 * DuplicatePattern (patternID, patternName)
	 * Return patternID
	 */
	public function DuplicatePattern($id, $name = false)
	{
		$duplicated_pattern_id = count($this->pattern);
		$this->pattern[] = $this->pattern[$id];
		if ($name)
			$this->SetPatternName($duplicated_pattern_id, $name);

		$this->changed = true;
		return $duplicated_pattern_id;
	}

	/**
	 * DeletePattern (patternID)
	 */
	public function DeletePattern($id)
	{
		unset ($this->pattern[$id]);
		$this->changed = true;
		return true;
	}

	/**
	 * SetPatternName (patternID, patternName)
	 */
	public function SetPatternName($id, $name)
	{
		$this->pattern[$id]['name'] = mb_convert_encoding($name, 'UCS-2') . "\x00\x00";    // Convert pattern name to UCS-2
		$this->changed = true;
		return true;
	}

	/**
	 * GetPatternFile (filename)
	 * Return file
	 */
	public function GetPatternFile($file = false)
	{
		if ($this->changed) {
			$this->rawdata = $this->header;
			$this->rawdata .= pack(
				'nN',
				$this->version,
				count($this->pattern)                     // Num of patterns
			);

			foreach ($this->pattern as $pattern) {
				switch ($pattern['type']) {
					case 1: // Gray
						$pallete = '';
						$space = 100;
						break;

					case 2: // Indexed
						$pallete = implode('', $pattern['pallete']);
						$pallete = pack(
							'H*@768ns',
							$pallete,                     // Make index
							count($pattern['pallete']),   // Num of pallete colors
							-1                            // Unknown
						);
						$space = 100;
						break;

					case 3: // RGB
						$pallete = '';
						$pattern['alpha'] = array_pop($pattern['channel']);
						if (preg_match('/^\x00*$/', $pattern['alpha']['data'])) {
							$pattern['alpha'] = false;
							$space = 92;
						} else {
							$space = 88;
						}
						break;

					default:
						return false;
						break;
				}

				$channel_data = '';

				foreach ($pattern['channel'] as $channel) {
					$channel_data .= $this->ChannelPack($channel);
				}

				$channel_data .= pack('x' . $space);
				if ($pattern['alpha'])
					$channel_data .= $this->ChannelPack($pattern['alpha']);

				$pattern_size = 20 + strlen($channel_data); // Size of pattern

				// Pack pattern header
				$this->rawdata .= pack(
						'N2n2N',
						$pattern['version'],              // Version
						$pattern['type'],                 // Image type
						$pattern['height'],               // Height
						$pattern['width'],                // Width
						(strlen($pattern['name']) / 2)    // Size of name
					)
					. $pattern['name']
					. "\x24" . pack('x36')         // $XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXX // Place for CLSID
					. $pallete                            // Pallete
					. pack(
						'N*',
						3,                           // Color model (always 0x03) %)
						$pattern_size,                    // Size of pattern
						$pattern['top'],                  // Top
						$pattern['left'],                 // Left
						$pattern['bottom'],               // Bottom
						$pattern['right'],                // Right
						$pattern['depth']                 // Depth
					);

				$this->rawdata .= $channel_data;

				$tag = md5($this->rawdata);               // Make CLSID
				$tag = substr($tag, 0, 8) . '-'
					 . substr($tag, 8, 4) . '-'
					 . substr($tag, 12, 4) . '-'
					 . substr($tag, 16, 4) . '-'
					 . substr($tag, 20, 22);

				$this->rawdata = substr_replace($this->rawdata, $tag, 27 + strlen($pattern['name']), 36); // Insert CLSID
			}
			$this->changed = false;
		}

		if ($file) {
			file_put_contents($file, $this->rawdata);
			return true;
		} else {
			return $this->rawdata;
		}
	}

	/**
	 * ChannelPack (binaryData)
	 * Return channel binary data
	 */
	private function ChannelPack($channel)
	{
		$width = $channel['right'] - $channel['left'];
		$height = $channel['bottom'] - $channel['top'];

		$channel_data_size = strlen($channel['data']);

		$channel_compressed_data = $this->RleEncoding($channel['data'], $width, $height);
		$channel_compressed_data_size = strlen($channel_compressed_data);

		// Compare sizes of compressed and raw data
		if ($channel_compressed_data_size < $channel_data_size) {
			$channel['compression'] = 1;
			$channel['data'] = $channel_compressed_data;
			$channel['size'] = 23 + $channel_compressed_data_size;
		}

		$channel['size'] = 23 + $channel_data_size;

		// Channel header
		return pack(
				'N7nC',
				$channel['version'],
				$channel['size'],
				$channel['depth'],
				$channel['top'],
				$channel['left'],
				$channel['bottom'],
				$channel['right'],
				$channel['depth'],
				$channel['compression']
			)
			. $channel['data'];
	}

	/**
	 * RleEncoding (binaryData)
	 * Return rle encoded data
	 */
	private function RleEncoding($input, $width, $height)
	{
		$output = '';
		$scanline_size = '';

		for ($row = 0; $row < $height; $row++) {
			$prev_byte = $input[$row * $width];
			$count = 0;
			$scanline = '';

			for ($col = 0; $col < $width; $col++) {
				$offset = $row * $width + $col;
				$byte = $input[$offset];

				if ($byte === $prev_byte && $width > $col + 1) {
					$count++;
				} else {
					if ($width === $col + 1)
						$count++;

					do {
						$count -= 128;

						if ($count < 0) {
							$repeat = -((128 + $count) - 1);
						} else {
							$repeat = -(128 - 1);
						}
						$scanline .= pack('c', $repeat);
						$scanline .= $prev_byte;
					} while ($count > 0);

					$count = 1;
				}

				$prev_byte = $byte;
			}

			$scanline_size .= pack('n', strlen($scanline));
			$output .= $scanline;
		}

		return $scanline_size . $output;
	}
}
