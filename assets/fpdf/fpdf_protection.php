<?php
/*******************************************************************************
* FPDF_Protection                                                              *
*                                                                              *
* Version: 1.1                                                                 *
* Date:    2011-12-11                                                          *
* Author:  Olivier PLATHEY                                                     *
*******************************************************************************/

require_once __DIR__ . '/fpdf.php';

class FPDF_Protection extends FPDF
{
	protected $encrypted;
	protected $Uvalue;
	protected $Ovalue;
	protected $Pvalue;
	protected $enc_obj_id;
	protected $encryption_key;
	protected $padding;

	public function SetProtection($permissions=array(), $user_pass='', $owner_pass=null)
	{
		$options = array('print'=>4, 'modify'=>16, 'copy'=>32, 'annot-forms'=>64);
		$p = 192;
		foreach($permissions as $permission)
		{
			if(isset($options[$permission]))
				$p += $options[$permission];
		}
		if($owner_pass===null)
			$owner_pass = uniqid(rand());
		$this->encrypted = true;
		$this->padding = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x66\x4E\x5E\x4E\x75\x8A\x41\x64\x66\x4E\x5E\x4E\x75\x8A\x41\x64\x66\x4E\x5E\x4E\x75\x8A\x41";
		$this->_generateencryptionkey($user_pass, $owner_pass, $p);
	}

	public function Line($x1, $y1, $x2, $y2)
	{
		parent::Line($x1, $y1, $x2, $y2);
	}

	public function Rect($x, $y, $w, $h, $style='')
	{
		parent::Rect($x, $y, $w, $h, $style);
	}

	public function _putencryption()
	{
		$this->_newobj();
		$this->enc_obj_id = $this->n;
		$this->_put('<<');
		$this->_put('/Filter /Standard');
		$this->_put('/V 1');
		$this->_put('/R 2');
		$this->_put('/O <'.bin2hex($this->Ovalue).'>');
 		$this->_put('/U <'.bin2hex($this->Uvalue).'>');
		$this->_put('/P '.$this->Pvalue);
		$this->_put('>>');
		$this->_put('endobj');
	}

	function _puttrailer_encryption()
 	{
 		if($this->encrypted)
 		{
 			$this->_put('/Encrypt '.$this->enc_obj_id.' 0 R');
 			$id = md5($this->Ovalue.$this->Uvalue);
 			$this->_put('/ID [<'.$id.'><'.$id.'>]');
 		}
 	}

	function _putheader()
	{
		if($this->encrypted)
			$this->PDFVersion = '1.3';
		parent::_putheader();
	}

	function _putstream($s)
	{
		if($this->encrypted)
			$s = $this->_RC4($this->_objectkey($this->n), $s);
		parent::_putstream($s);
	}

	function _textstring($s)
 	{
 		if($this->encrypted)
 			return '<'.bin2hex($this->_RC4($this->_objectkey($this->n), $s)).'>';
 		return parent::_textstring($s);
 	}

	protected function _objectkey($n)
	{
		return substr($this->_md5($this->encryption_key.substr(pack('V', $n), 0, 3).pack('v', 0)), 0, 10);
	}

	function _generateencryptionkey($user_pass, $owner_pass, $p)
	{
		$p = -(($p^255)+1);
		$this->Pvalue = $p;
		if($user_pass===null)
			$user_pass = '';
		if($owner_pass===null)
			$owner_pass = '';
		$o = $this->_Ovalue($user_pass, $owner_pass);
 		$this->Ovalue = $o;
 		$this->encryption_key = substr($this->_md5($this->_padding($user_pass).$o.pack('V', $p)."\xFF\xFF\xFF\xFF"), 0, 5);
 		$this->Uvalue = $this->_Uvalue($user_pass);
 	}

	function _padding($s)
	{
		$len = strlen($s);
		if($len>32)
			$s = substr($s, 0, 32);
		else if($len<32)
			$s .= substr($this->padding, 0, 32-$len);
		return $s;
	}

	function _md5($s)
 	{
 		return md5($s, true);
 	}
 
 	function _Ovalue($user_pass, $owner_pass)
 	{
 		$tmp = $this->_md5($this->_padding($owner_pass));
 		$key = substr($tmp, 0, 5);
 		return $this->_RC4($key, $this->_padding($user_pass));
 	}
 
 	function _Uvalue($user_pass)
 	{
 		return $this->_RC4($this->encryption_key, $this->padding);
 	}
 
 	function _RC4($key, $data)
 	{
 		static $last_key, $last_state;
 
 		if($key != $last_key)
 		{
 			if(strlen($key)==0)
 				return $data;
 			$k = str_repeat($key, 256/strlen($key)+1);
 			$state = range(0, 255);
 			$j = 0;
 			for($i=0; $i<256; $i++)
 			{
 				$j = ($j + $state[$i] + ord($k[$i])) % 256;
 				$t = $state[$i];
 				$state[$i] = $state[$j];
 				$state[$j] = $t;
 			}
 			$last_key = $key;
 			$last_state = $state;
 		}
 		else
 			$state = $last_state;
 
 		$len = strlen($data);
 		$j = 0;
 		$i = 0;
 		$res = '';
 		for($k=0; $k<$len; $k++)
 		{
 			$i = ($i + 1) % 256;
 			$j = ($j + $state[$i]) % 256;
 			$t = $state[$i];
 			$state[$i] = $state[$j];
 			$state[$j] = $t;
 			$res .= $data[$k] ^ chr($state[($state[$i] + $state[$j]) % 256]);
 		}
 		return $res;
 	}
}
?>