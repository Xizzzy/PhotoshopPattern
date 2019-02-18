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
 */

class PhotoshopPattern
{

	/**
	 *	Raw data of pattern file
	 */
	private $rawdata = '';
	
	/**
	 *	Pattern file header
	 */
	private $header  = "\x38\x42\x50\x54"; // 8BPT
	
	/**
	 *	Default version
	 */
	private $version = 1;
	
	/**
	 *	Array for patterns
	 */
	private $pattern = array ();
	
	/**
	 *	Flag for status
	 */
	private $changed = false;

	/**
	 *	Constructor
	 */
	public function __construct ($version = false)
	{
		if ($version)
			$this->version = $version;
	}

	/**
	 *	CreatePattern (width, height, patternName, type)
	 *	Return patternID
	 */
	public function CreatePattern ($width, $height, $name, $type)
	{
		$id = count ($this->pattern);

		$this->pattern[$id]['pallete'] = array ();
		$this->pattern[$id]['channel'] = array ();
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
		$this->SetPatternName ($id, $name);

		switch ($type)
		{
			case 1: // Gray
				$this->CreateChannel ($id, $width, $height);
				break;

			case 2: // Indexed
				$this->CreateChannel ($id, $width, $height);
				break;

			case 3: // RGB
				$this->CreateChannel ($id, $width, $height); // Red
				$this->CreateChannel ($id, $width, $height); // Green
				$this->CreateChannel ($id, $width, $height); // Blue
				$this->CreateChannel ($id, $width, $height); // Alpha
				break;

			default:
				return false;
				break;
		}

		$this->changed = true;
		return $id;
	}

	/**
	 *	CreateChannel (patternID, Width, Height)
	 *	Return channel id
	 */
	private function CreateChannel ($id, $width, $height)
	{
		$channel_id = count ($this->pattern[$id]['channel']);
		$channel_data_size = $width * $height;

		$this->pattern[$id]['channel'][$channel_id]['version'] = 1;
		$this->pattern[$id]['channel'][$channel_id]['size'] = 23 + $channel_data_size;
		$this->pattern[$id]['channel'][$channel_id]['top'] = 0;
		$this->pattern[$id]['channel'][$channel_id]['left'] = 0;
		$this->pattern[$id]['channel'][$channel_id]['bottom'] = $height;
		$this->pattern[$id]['channel'][$channel_id]['right'] = $width;
		$this->pattern[$id]['channel'][$channel_id]['depth'] = 8; // Bit per channel
		$this->pattern[$id]['channel'][$channel_id]['compression'] = 0; // RLE compression
		$this->pattern[$id]['channel'][$channel_id]['data'] = pack ('x' . $channel_data_size);

		return $channel_id;
	}

	/**
	 *	ColorAllocate (patternID, RGB, [transparance percent])
	 *	Return colorID
	 */
	public function ColorAllocate ($id, $color, $transparance = 0)
	{
		if ($color_id = array_search ($color, $this->pattern[$id]['pallete']))
			return $color_id;

		$color_id = count ($this->pattern[$id]['pallete']);

		switch ($this->pattern[$id]['type'])
		{
			case 1: // Gray
				if (preg_match ('/^[a-f0-9]{2}$/i', $color))
				{
					$this->pattern[$id]['pallete'][] = $color;
				}
				else return false;
				break;

			case 2: // Indexed
				if (preg_match ('/^[a-f0-9]{6}$/i', $color))
				{
					if ($color_id < 257)
					{
						$this->pattern[$id]['pallete'][] = $color;
					}
					else return false;
				}
				else return false;
				break;

			case 3: // RGB or RGBA
				if (preg_match ('/^[a-f0-9]{6}$/i', $color))
				{
					if ($transparance < 1)
					{
						$this->pattern[$id]['pallete'][] = $color . '00';
					}
					else if ($transparance > 0 && $transparance < 101)
					{
						$this->pattern[$id]['pallete'][] = $color . str_pad (dechex (round ($transparance * 255 / 100)), 2, '0', STR_PAD_LEFT);
					}
					else
					{
						$this->pattern[$id]['pallete'][] = $color . 'ff';
					}
				}
				else if (preg_match ('/^[a-f0-9]{8}$/i', $color))
				{
					$this->pattern[$id]['pallete'][] = $color;
				}
				else return false;
				break;

			default:
				return false;
				break;
		}

		$this->changed = true;
		return $color_id;
	}

	/**
	 *	DrawRect (patternID, startPositionX, startPositionY, width, height, colorID)
	 */
	public function DrawRect ($id, $x1, $y1, $width, $height, $color)
	{
		$x2 = $x1 + $width;
		$y2 = $y1 + $height;

		if ($x1 > $this->pattern[$id]['width'] || $x2 > $this->pattern[$id]['width'] || $y1 > $this->pattern[$id]['height'] || $y2 > $this->pattern[$id]['height'])
			return false;

		switch ($this->pattern[$id]['type'])
		{
			case 1: // Gray
				for ($x = $x1; $x < $x2; $x++)
				{
					for ($y = $y1; $y < $y2; $y++)
					{
						$this->pattern[$id]['channel'][0]['data'][$y * $this->pattern[$id]['width'] + $x] = pack ('H*', $this->pattern[$id]['pallete'][$color]);
					}
				}
				break;

			case 2: // Indexed
				for ($x = $x1; $x < $x2; $x++)
				{
					for ($y = $y1; $y < $y2; $y++)
					{
						$this->pattern[$id]['channel'][0]['data'][$y * $this->pattern[$id]['width'] + $x] = pack ('C', $color);
					}
				}
				break;

			case 3: // RGB
				for ($x = $x1; $x < $x2; $x++)
				{
					for ($y = $y1; $y < $y2; $y++)
					{
						preg_match_all ('/[a-f0-9]{2}/i', $this->pattern[$id]['pallete'][$color], $matches);
						foreach ($matches[0] as $channel => $value)
						{
							$this->pattern[$id]['channel'][$channel]['data'][$y * $this->pattern[$id]['width'] + $x] = pack ('H*', $value);
						}
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
	 *	DrawPixel (patternID, startPositionX, startPositionY, colorID)
	 */
	public function DrawPixel ($id, $x, $y, $color)
	{
		switch ($this->pattern[$id]['type'])
		{
			case 1: // Gray
				$this->pattern[$id]['channel'][0]['data'][$y * $this->pattern[$id]['width'] + $x] = pack ('H*', $this->pattern[$id]['pallete'][$color]);
				break;

			case 2: // Indexed
				$this->pattern[$id]['channel'][0]['data'][$y * $this->pattern[$id]['width'] + $x] = pack ('C', $color);
				break;

			case 3: // RGB
				preg_match_all ('/[a-f0-9]{2}/i', $this->pattern[$id]['pallete'][$color], $matches);
				foreach ($matches[0] as $channel => $value)
				{
					$this->pattern[$id]['channel'][$channel]['data'][$y * $this->pattern[$id]['width'] + $x] = pack ('H*', $value);
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
	 *	DuplicatePattern (patternID, patternName)
	 *	Return patternID
	 */
	public function DuplicatePattern ($id, $name = false)
	{
		$duplicated_pattern_id = count ($this->pattern);
		$this->pattern[] = $this->pattern[$id];
		if ($name)
			$this->SetPatternName ($duplicated_pattern_id, $name);

		$this->changed = true;
		return $duplicated_pattern_id;
	}

	/**
	 *	DeletePattern (patternID)
	 */
	public function DeletePattern ($id)
	{
		unset ($this->pattern[$id]);
		$this->changed = true;
		return true;
	}

	/**
	 *	SetPatternName (patternID, patternName)
	 */
	public function SetPatternName ($id, $name)
	{
		$this->pattern[$id]['name'] = iconv ('UTF-8', 'UCS-2', $name) . "\x00\x00";	// Convert pattern name to UCS-2
		$this->changed = true;
		return true;
	}

	/**
	 *	GetPatternFile (filename)
	 *	Return file
	 */
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
				$hash = $pattern['name'].$pattern['width'].$pattern['height'];
				foreach ($pattern['channel'] as $channel)
				{
					$hash .= $channel['data'];
				}
				
				$channels = count ($pattern['channel']);
				
				switch ($pattern['type'])
				{
					case 1: // Gray
						$tag = md5 ($hash);	// Make CLSID
						$pallete = '';
						$space = 100;
						break;

					case 2: // Indexed
						$pallete = implode('', $pattern['pallete']);
						$tag = md5 ($pallete.$hash);	// Make CLSID
						$pallete = pack (
														'H*@768ns',
														$pallete,		// Make index
														count($pattern['pallete']),			// Num of pallete colors
														-1															// Unknown
														);
						$space = 100;
						break;

					case 3: // RGB
						$tag = md5 ($hash);	// Make CLSID
						$pallete = '';
						$pattern['alpha'] = array_pop ($pattern['channel']);
						if (preg_match ('/^\x00*$/', $pattern['alpha']['data']))
						{
							$pattern['alpha'] = false;
							$space = 92;
							$channels--;
						}
						else
						{
							$space = 88;
						}
						break;

					default:
						return false;
						break;
				}
				
				unset ($hash);
				
				$tag = substr($tag, 0, 8).'-'
							.substr($tag, 8, 4).'-'
							.substr($tag, 12, 4).'-'
							.substr($tag, 16, 4).'-'
							.substr($tag, 20, 22);

				$pattern_size = 20 + ((31 + $pattern['width'] * $pattern['height']) * $channels) + $space; // Size of pattern

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
												. $pallete													// Pallete
												. pack (
														'N*',
														3,															// Color model (always 0x03) %)
														$pattern_size,									// Size of pattern
														$pattern['top'],								// Top
														$pattern['left'],								// Left
														$pattern['bottom'],							// Bottom
														$pattern['right'],							// Right
														$pattern['depth']								// Depth
													);

				foreach ($pattern['channel'] as $channel)
				{
					$this->rawdata .= $this->ChannelPack ($channel);
				}
				
				$this->rawdata .= pack ('x' . $space);
				if ($pattern['alpha'])
					$this->rawdata .= $this->ChannelPack ($pattern['alpha']);
				
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

	/**
	 *	ChannelPack (binaryData)
	 *	Return channel binary data
	 */
	private function ChannelPack ($channel)
	{
		// Pack channel header
		return pack (
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
	 *	RleEncoding (binaryData)
	 *	Return rle encoded data
	 */
	private function RleEncoding ($input)
	{
		// Need more memory and time
	}
}

?>