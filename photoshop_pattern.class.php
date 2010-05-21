<?php

class PhotoshopPattern
{

	private $rawdata = '';
	private $header  = "\x38\x42\x50\x54"; // 8BPT
	private $version = 1;
	private $pattern = array ();
	private $changed = false;

	public function __construct ($version = false)
	{
		if ($version)
			$this->version = $version;
	}

	// CreatePattern (width, height, patternName, type)
	// Return patternID
	public function CreatePattern ($width, $height, $name, $type = 2)
	{
		$id = count ($this->pattern);
		$this->pattern[$id]['pallete'] = array ();
		$this->pattern[$id]['version'] = 1;
		$this->pattern[$id]['type'] = $type;
		$this->pattern[$id]['height'] = $height;
		$this->pattern[$id]['width'] = $width;
		$this->pattern[$id]['top'] = 0;
		$this->pattern[$id]['left'] = 0;
		$this->pattern[$id]['bottom'] = $height;
		$this->pattern[$id]['right'] = $width;
		$this->pattern[$id]['depth'] = 24;
		$this->SetPatternName ($id, $name);

		switch ($type)
		{
			case 1: // Gray
				$this->pattern[$id]['channels'] = 1;
				break;

			case 2: // Indexed
				$this->pattern[$id]['channels'] = 1;
				break;

			case 3: // RGB
				$this->pattern[$id]['channels'] = 3;
				break;

			default:
				return false;
				break;
		}

		$channel_data_size = $width * $height;

		$this->pattern[$id]['size'] = 120 + (31 * $this->pattern[$id]['channels']) + ($channel_data_size * $this->pattern[$id]['channels']); // Size of pattern

		for ($i = 0; $i < $this->pattern[$id]['channels']; $i++)
		{
			$this->pattern[$id]['channel'][$i]['version'] = 1;
			$this->pattern[$id]['channel'][$i]['size'] = 23 + $channel_data_size;
			$this->pattern[$id]['channel'][$i]['top'] = 0;
			$this->pattern[$id]['channel'][$i]['left'] = 0;
			$this->pattern[$id]['channel'][$i]['bottom'] = $height;
			$this->pattern[$id]['channel'][$i]['right'] = $width;
			$this->pattern[$id]['channel'][$i]['depth'] = 8; // Bit per channel
			$this->pattern[$id]['channel'][$i]['compression'] = 0; // RLE compression
			$this->pattern[$id]['channel'][$i]['data'] = pack ('x' . $channel_data_size);
		}

		$this->changed = true;

		return $id;
	}

	// ColorAllocate (patternID, RGBColor)
	// Return colorID
	public function ColorAllocate ($id, $rgb)
	{
		if (!preg_match ('/^[A-Fa-f0-9]{6}$/', $rgb, $matches))
			return false;

		$rgb = strtolower ($rgb);

		if ($color_id = array_search ($rgb, $this->pattern[$id]['pallete']))
			return $color_id;

		if (($color_id = count ($this->pattern[$id]['pallete'])) < 257)
		{
			$this->pattern[$id]['pallete'][] = $rgb;
			return $color_id;
		}

		$this->changed = true;

		return false;
	}

	// DrawRect (patternID, startPositionX, startPositionY, width, height, colorID)
	public function DrawRect ($id, $x1, $y1, $width, $height, $color)
	{
		$x2 = $x1 + $width;
		$y2 = $y1 + $height;

		if ($x1 > $this->pattern[$id]['width'] || $x2 > $this->pattern[$id]['width'] || $y1 > $this->pattern[$id]['height'] || $y2 > $this->pattern[$id]['height'])
			return false;

		$color = pack ('C', $color);

		for ($x = $x1; $x < $x2; $x++)
		{
			for ($y = $y1; $y < $y2; $y++)
			{
				$this->pattern[$id]['channel'][0]['data'][$y * $this->pattern[$id]['width'] + $x] = $color;
			}
		}

		$this->changed = true;

		return true;
	}

	// DrawPixel (patternID, startPositionX, startPositionY, colorID)
	public function DrawPixel ($id, $x, $y, $color)
	{
		$this->pattern[$id]['channel'][0]['data'][$y * $this->pattern[$id]['width'] + $x] = pack ('C', $color);
		$this->changed = true;
		return true;
	}

	// DuplicatePattern (patternID, patternName)
	// Return patternID
	public function DuplicatePattern ($id, $name = false)
	{
		$duplicated_pattern_id = count ($this->pattern);
		$this->pattern[] = $this->pattern[$id];
		if ($name)
			$this->SetPatternName ($duplicated_pattern_id, $name);

		$this->changed = true;

		return $duplicated_pattern_id;
	}

	// DeletePattern (patternID)
	public function DeletePattern ($id)
	{
		unset ($this->pattern[$id]);

		$this->changed = true;

		return true;
	}

	// SetPatternName (patternID, patternName)
	public function SetPatternName ($id, $name)
	{
		$this->pattern[$id]['name'] = iconv ('UTF-8', 'UCS-2', $name) . "\x00\x00";	// Convert pattern name to UCS-2

		$this->changed = true;

		return true;
	}

	// GetPatternFile (filename)
	// Return file
	public function GetPatternFile ($file = false)
	{
		if ($this->changed)
		{
			$this->rawdata = $this->header;
			$this->rawdata .= pack (
													'nN',
													$this->version,
													count ($this->pattern)					// Num of patterns
												);

			foreach ($this->pattern as $pattern)
			{
				$tag = md5 (implode($pattern['pallete']).$pattern['channel'][0]['data']);	// Make CLSID
				$tag = substr($tag, 0, 8).'-'
							.substr($tag, 8, 4).'-'
							.substr($tag, 12, 4).'-'
							.substr($tag, 16, 4).'-'
							.substr($tag, 20, 22);

				// Pack pattern header
				$this->rawdata .= pack (
														'N2n2N',
														$pattern['version'],						// Version
														$pattern['type'],								// Image type
														$pattern['height'],							// Height
														$pattern['width'],							// Width
														(strlen ($pattern['name']) / 2)	// Size of name
													)
												. $pattern['name']
												. "\x24"														// $
												. $tag															// XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXX
												. pack (
														'H*@768nsN*',
														implode($pattern['pallete']),		// Make index
														count($pattern['pallete']),			// Num of pallete colors
														-1,															// Unknown
														3,															// Color model (always 0x03) %)
														$pattern['size'],								// Size of pattern
														$pattern['top'],								// Top
														$pattern['left'],								// Left
														$pattern['bottom'],							// Bottom
														$pattern['right'],							// Right
														$pattern['depth']								// Depth
													);

				foreach ($pattern['channel'] as $channel)
				{
					// Pack channel header
					$this->rawdata .= pack (
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
													. $channel['data']
													. pack (
															'x100'
													);
				}
			}
			$this->changed = false;
		}

		if ($file)
		{
			file_put_contents ($file, $this->rawdata);
			return true;
		}
		else
		{
			#header ('Content-Type: application/octet-stream');
			#header ('Content-Disposition: attachment; filename="'.$file.'"');
			return $this->rawdata;
		}
	}

	private function RleEncoding ($input)
	{
		// Need more memory and time
	}
}

?>