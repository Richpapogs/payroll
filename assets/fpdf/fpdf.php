<?php
/*******************************************************************************
* FPDF                                                                         *
*                                                                              *
* Version: 1.86                                                                *
* Date:    2023-06-25                                                          *
* Author:  Olivier PLATHEY                                                     *
*******************************************************************************/

define('FPDF_VERSION','1.86');

class FPDF
{
protected $page;               // current page number
protected $n;                  // current object number
protected $offsets;            // array of object offsets
protected $buffer;             // buffer holding in-memory PDF
protected $pages;              // array containing pages
protected $state;              // current document state
protected $compress;           // compression flag
protected $k;                  // scale factor (number of points in user unit)
protected $CurOrientation;     // current orientation
protected $StdPageSize;        // standard page sizes
protected $DefPageSize;        // default page size
protected $CurPageSize;        // current page size
protected $CurRotation;        // current page rotation
protected $PageSizes;          // used for different page sizes
protected $PageRoot;           // Pages root object ID
 	protected $res_obj_id;         // Resource dictionary object ID
 	protected $first_page_id;      // First page object ID
protected $Terminated;         // whether terminate() has been called
protected $instancier;         // instancier for PHP 8.2+
protected $InHeader;           // flag set when processing header
protected $InFooter;           // flag set when processing footer
protected $AliasNbPages;       // alias for total number of pages
protected $CurFontSizePt;      // current font size in points
protected $FontFamily;         // current font family
protected $FontStyle;          // current font style
protected $underline;          // underlining flag
protected $CurrentFont;        // current font info
protected $FontSizePt;         // default font size in points
protected $FontSize;           // current font size in user unit
protected $DrawColor;          // commands for drawing color
protected $FillColor;          // commands for filling color
protected $TextColor;          // commands for text color
protected $ColorFlag;          // whether fill color and text color are different
protected $WithAlpha;          // whether alpha channel is used
protected $ws;                 // word spacing
protected $fonts;              // array of used fonts
protected $FontFiles;          // array of used font files
protected $encodings;          // array of used encodings
protected $cmaps;              // array of used CMaps
protected $diffs;              // array of encoding differences
protected $images;             // array of used images
protected $PageLinks;          // array of links in pages
protected $links;              // array of internal links
protected $AutoPageBreak;      // automatic page breaking
protected $PageBreakTrigger;   // threshold used to trigger page breaks
protected $InContents;         // flag set when writing to page contents
protected $CurX;               // current x position
protected $CurY;               // current y position
protected $lasth;              // height of last printed cell
protected $LineWidth;          // line width in user unit
protected $fontpath;           // path containing fonts
protected $CoreFonts;          // array of core PDF fonts
protected $extgstates;         // array of extended graphic states

/*******************************************************************************
*                               Public methods                                 *
*******************************************************************************/

function __construct($orientation='P', $unit='mm', $size='A4')
{
	// Some checks
	$this->_dochecks();
	// Initialization of properties
	$this->state = 0;
	$this->page = 0;
	$this->n = 0;
	$this->buffer = '';
	$this->pages = array();
	$this->PageSizes = array();
	$this->state = 0;
	$this->fonts = array();
	$this->FontFiles = array();
	$this->diffs = array();
	$this->encodings = array();
	$this->cmaps = array();
	$this->images = array();
	$this->links = array();
	$this->InContents = false;
	$this->InHeader = false;
	$this->InFooter = false;
	$this->lasth = 0;
	$this->FontFamily = '';
	$this->FontStyle = '';
	$this->FontSizePt = 12;
	$this->underline = false;
	$this->DrawColor = '0 G';
	$this->FillColor = '0 g';
	$this->TextColor = '0 g';
	$this->ColorFlag = false;
	$this->WithAlpha = false;
	$this->ws = 0;
	// Font path
	if(defined('FPDF_FONTPATH'))
	{
		$this->fontpath = FPDF_FONTPATH;
		if(substr($this->fontpath,-1)!='/' && substr($this->fontpath,-1)!='\\')
			$this->fontpath .= '/';
	}
	elseif(is_dir(dirname(__FILE__).'/font'))
		$this->fontpath = dirname(__FILE__).'/font/';
	else
		$this->fontpath = '';
	// Core fonts
	$this->CoreFonts = array('courier'=>'Courier', 'helvetica'=>'Helvetica', 'times'=>'Times-Roman', 'symbol'=>'Symbol', 'zapfdingbats'=>'ZapfDingbats');
	// Scale factor
	if($unit=='pt')
		$this->k = 1;
	elseif($unit=='mm')
		$this->k = 72/25.4;
	elseif($unit=='cm')
		$this->k = 72/2.54;
	elseif($unit=='in')
		$this->k = 72;
	else
		$this->Error('Incorrect unit: '.$unit);
	// Page sizes
	$this->StdPageSize = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28),
		'letter'=>array(612,792), 'legal'=>array(612,1008));
	$size = $this->_getpagesize($size);
	$this->DefPageSize = $size;
	$this->CurPageSize = $size;
	// Page orientation
	$orientation = strtolower($orientation);
	if($orientation=='p' || $orientation=='portrait')
	{
		$this->DefOrientation = 'P';
		$this->w = $size[0]/$this->k;
		$this->h = $size[1]/$this->k;
	}
	elseif($orientation=='l' || $orientation=='landscape')
	{
		$this->DefOrientation = 'L';
		$this->w = $size[1]/$this->k;
		$this->h = $size[0]/$this->k;
	}
	else
		$this->Error('Incorrect orientation: '.$orientation);
	$this->CurOrientation = $this->DefOrientation;
	$this->wPt = $this->w*$this->k;
	$this->hPt = $this->h*$this->k;
	// Page rotation
	$this->CurRotation = 0;
	// Page margins (1 cm)
	$margin = 28.35/$this->k;
	$this->SetMargins($margin,$margin);
	// Interior cell margin (1 mm)
	$this->cMargin = $margin/10;
	// Line width (0.2 mm)
	$this->LineWidth = .567/$this->k;
	// Automatic page break
	$this->SetAutoPageBreak(true,2*$margin);
	// Default display mode
	$this->SetDisplayMode('default');
	// Enable compression
	$this->SetCompression(true);
	// Set PDF version
	$this->PDFVersion = '1.3';
}

function SetMargins($left, $top, $right=null)
{
	// Set left, top and right margins
	$this->lMargin = $left;
	$this->tMargin = $top;
	if($right===null)
		$right = $left;
	$this->rMargin = $right;
}

function SetLeftMargin($margin)
{
	// Set left margin
	$this->lMargin = $margin;
	if($this->page>0 && $this->CurX<$margin)
		$this->CurX = $margin;
}

function SetTopMargin($margin)
{
	// Set top margin
	$this->tMargin = $margin;
}

function SetRightMargin($margin)
{
	// Set right margin
	$this->rMargin = $margin;
}

function SetAutoPageBreak($auto, $margin=0)
{
	// Set auto page break mode and triggering margin
	$this->AutoPageBreak = $auto;
	$this->bMargin = $margin;
	$this->PageBreakTrigger = $this->h-$margin;
}

function SetDisplayMode($zoom, $layout='default')
{
	// Set display mode in viewer
	if($zoom=='fullpage' || $zoom=='fullwidth' || $zoom=='real' || $zoom=='default' || !is_string($zoom))
		$this->ZoomMode = $zoom;
	else
		$this->Error('Incorrect zoom display mode: '.$zoom);
	if($layout=='single' || $layout=='continuous' || $layout=='two' || $layout=='default')
		$this->LayoutMode = $layout;
	else
		$this->Error('Incorrect layout display mode: '.$layout);
}

function SetCompression($compress)
{
	// Set page compression
	if(function_exists('gzcompress'))
		$this->compress = $compress;
	else
		$this->compress = false;
}

function SetTitle($title, $isUTF8=false)
{
	// Title of document
	$this->title = $title;
	$this->titleUTF8 = $isUTF8;
}

function SetSubject($subject, $isUTF8=false)
{
	// Subject of document
	$this->subject = $subject;
	$this->subjectUTF8 = $isUTF8;
}

function SetAuthor($author, $isUTF8=false)
{
	// Author of document
	$this->author = $author;
	$this->authorUTF8 = $isUTF8;
}

function SetKeywords($keywords, $isUTF8=false)
{
	// Keywords of document
	$this->keywords = $keywords;
	$this->keywordsUTF8 = $isUTF8;
}

function SetCreator($creator, $isUTF8=false)
{
	// Creator of document
	$this->creator = $creator;
	$this->creatorUTF8 = $isUTF8;
}

function AliasNbPages($alias='{nb}')
{
	// Define an alias for total number of pages
	$this->AliasNbPages = $alias;
}

function Error($msg)
{
	// Fatal error
	throw new Exception('FPDF error: '.$msg);
}

function Close()
{
	// Terminate document
	if($this->state==3)
		return;
	if($this->page==0)
		$this->AddPage();
	// Page footer
	$this->InFooter = true;
	$this->Footer();
	$this->InFooter = false;
	// Close page
	$this->_endpage();
	// Close document
	$this->_enddoc();
}

function AddPage($orientation='', $size='', $rotation=0)
{
	// Start a new page
	if($this->state==3)
		$this->Error('The document is closed');
	$family = $this->FontFamily;
	$style = $this->FontStyle.($this->underline ? 'U' : '');
	$fontsize = $this->FontSizePt;
	$lw = $this->LineWidth;
	$dc = $this->DrawColor;
	$fc = $this->FillColor;
	$tc = $this->TextColor;
	$cf = $this->ColorFlag;
	if($this->page>0)
	{
		// Page footer
		$this->InFooter = true;
		$this->Footer();
		$this->InFooter = false;
		// Close page
		$this->_endpage();
	}
	// Start new page
	$this->_beginpage($orientation,$size,$rotation);
	// Set line cap style to square
	$this->_out('2 J');
	// Set line width
	$this->LineWidth = $lw;
	$this->_out(sprintf('%.2F w',$lw*$this->k));
	// Set font
	if($family)
		$this->SetFont($family,$style,$fontsize);
	// Set colors
	$this->DrawColor = $dc;
	if($dc!='0 G')
		$this->_out($dc);
	$this->FillColor = $fc;
	if($fc!='0 g')
		$this->_out($fc);
	$this->TextColor = $tc;
	$this->ColorFlag = $cf;
	// Page header
	$this->InHeader = true;
	$this->Header();
	$this->InHeader = false;
	// Restore line width
	if($this->LineWidth!=$lw)
	{
		$this->LineWidth = $lw;
		$this->_out(sprintf('%.2F w',$lw*$this->k));
	}
	// Restore font
	if($family)
		$this->SetFont($family,$style,$fontsize);
	// Restore colors
	if($this->DrawColor!=$dc)
	{
		$this->DrawColor = $dc;
		$this->_out($dc);
	}
	if($this->FillColor!=$fc)
	{
		$this->FillColor = $fc;
		$this->_out($fc);
	}
	$this->TextColor = $tc;
	$this->ColorFlag = $cf;
}

function Header()
{
	// To be implemented in your own inherited class
}

function Footer()
{
	// To be implemented in your own inherited class
}

function PageNo()
{
	// Get current page number
	return $this->page;
}

function SetDrawColor($r, $g=null, $b=null)
{
	// Set color for all stroking operations
	if(($r==0 && $g==0 && $b==0) || $g===null)
		$this->DrawColor = sprintf('%.3F G',$r/255);
	else
		$this->DrawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
	if($this->page>0)
		$this->_out($this->DrawColor);
}

function SetFillColor($r, $g=null, $b=null)
{
	// Set color for all filling operations
	if(($r==0 && $g==0 && $b==0) || $g===null)
		$this->FillColor = sprintf('%.3F g',$r/255);
	else
		$this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
	$this->ColorFlag = ($this->FillColor!=$this->TextColor);
	if($this->page>0)
		$this->_out($this->FillColor);
}

function SetTextColor($r, $g=null, $b=null)
{
	// Set color for text
	if(($r==0 && $g==0 && $b==0) || $g===null)
		$this->TextColor = sprintf('%.3F g',$r/255);
	else
		$this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
	$this->ColorFlag = ($this->FillColor!=$this->TextColor);
}

function GetStringWidth($s)
{
	// Get width of a string in the current font
	$s = (string)$s;
	$cw = &$this->CurrentFont['cw'];
	$w = 0;
	$l = strlen($s);
	for($i=0;$i<$l;$i++)
 		$w += $cw[$s[$i]] ?? 600;
	return $w*$this->FontSize/1000;
}

function SetLineWidth($width)
{
	// Set line width
	$this->LineWidth = $width;
	if($this->page>0)
		$this->_out(sprintf('%.2F w',$width*$this->k));
}

function Line($x1, $y1, $x2, $y2)
{
	// Draw a line
	$this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->k,($this->h-$y1)*$this->k,$x2*$this->k,($this->h-$y2)*$this->k));
}

function Rect($x, $y, $w, $h, $style='')
{
	// Draw a rectangle
	if($style=='F')
		$op = 'f';
	elseif($style=='FD' || $style=='DF')
		$op = 'B';
	else
		$op = 'S';
	$this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->k,($this->h-$y)*$this->k,$w*$this->k,-$h*$this->k,$op));
}

function SetFont($family, $style='', $size=0)
{
	// Select a font; size given in points
	if($family=='')
		$family = $this->FontFamily;
	else
		$family = strtolower($family);
	$style = strtoupper($style);
	if(strpos($style,'U')!==false)
	{
		$this->underline = true;
		$style = str_replace('U','',$style);
	}
	else
		$this->underline = false;
	if($style=='IB')
		$style = 'BI';
	if($size==0)
		$size = $this->FontSizePt;

	// Test if font is already selected
	if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size)
		return;

	// Check if font is one of the core fonts
	if($family=='arial')
		$family = 'helvetica';
	if(array_key_exists($family,$this->CoreFonts))
 		{
 			if($family=='symbol' || $family=='zapfdingbats')
 				$style = '';
 			$fontkey = $family.$style;
 			if(!isset($this->fonts[$fontkey]))
 			{
 				// Load font metrics
 				$i = count($this->fonts)+1;
 				$this->fonts[$fontkey] = array('i'=>$i, 'type'=>'core', 'name'=>$this->CoreFonts[$family], 'up'=>-100, 'ut'=>50, 'cw'=>array()); 
 				// Basic CW for Helvetica (Simplified)
 				$cw = array_fill(0, 255, 600);
 				foreach($cw as $k=>$v) $this->fonts[$fontkey]['cw'][chr($k)] = $v;
 			}
		$this->FontFamily = $family;
		$this->FontStyle = $style;
		$this->FontSizePt = $size;
		$this->FontSize = $size/$this->k;
		$this->CurrentFont = &$this->fonts[$fontkey];
		if($this->page>0)
			$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
	}
	else
		$this->Error('Undefined font: '.$family.' '.$style);
}

function SetFontSize($size)
{
	// Set font size in points
	if($this->FontSizePt==$size)
		return;
	$this->FontSizePt = $size;
	$this->FontSize = $size/$this->k;
	if($this->page>0)
		$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function AddLink()
{
	// Create a new internal link
	$n = count($this->links)+1;
	$this->links[$n] = array(0, 0);
	return $n;
}

function SetLink($link, $y=0, $page=-1)
{
	// Set destination of internal link
	if($y==-1)
		$y = $this->CurY;
	if($page==-1)
		$page = $this->page;
	$this->links[$link] = array($page, $y);
}

function Link($x, $y, $w, $h, $link)
{
	// Put a link on the page
	$this->PageLinks[$this->page][] = array($x*$this->k, $this->hPt-$y*$this->k, $w*$this->k, $h*$this->k, $link);
}

function Text($x, $y, $txt)
{
	// Output a string
	if(!isset($this->CurrentFont))
		$this->Error('No font has been set');
	$s = sprintf('BT %.2F %.2F Td (%s) Tj ET',$x*$this->k,($this->h-$y)*$this->k,$this->_escape($txt));
	if($this->underline && $txt!='')
		$s .= ' '.$this->_dounderline($x,$y,$txt);
	if($this->ColorFlag)
		$s = 'q '.$this->TextColor.' '.$s.' Q';
	$this->_out($s);
}

function AcceptPageBreak()
{
	// Accept automatic page break or not
	return $this->AutoPageBreak;
}

function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
{
	// Output a cell
	$k = $this->k;
	if($this->CurY+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
	{
		// Automatic page break
		$x = $this->CurX;
		$ws = $this->ws;
		if($ws>0)
		{
			$this->ws = 0;
			$this->_out('0 Tw');
		}
		$this->AddPage($this->CurOrientation,$this->CurPageSize,$this->CurRotation);
		$this->CurX = $x;
		if($ws>0)
		{
			$this->ws = $ws;
			$this->_out(sprintf('%.3F Tw',$ws*$k));
		}
	}
	if($w==0)
		$w = $this->w-$this->rMargin-$this->CurX;
	$s = '';
	if($fill || $border==1)
	{
		if($fill)
			$op = ($border==1) ? 'B' : 'f';
		else
			$op = 'S';
		$s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->CurX*$k,($this->h-$this->CurY)*$k,$w*$k,-$h*$k,$op);
	}
	if(is_string($border))
	{
		$x = $this->CurX;
		$y = $this->CurY;
		if(strpos($border,'L')!==false)
			$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
		if(strpos($border,'T')!==false)
			$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
		if(strpos($border,'R')!==false)
			$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
		if(strpos($border,'B')!==false)
			$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
	}
	if($txt!=='')
	{
		if(!isset($this->CurrentFont))
			$this->Error('No font has been set');
		if($align=='R')
			$dx = $w-$this->cMargin-$this->GetStringWidth($txt);
		elseif($align=='C')
			$dx = ($w-$this->GetStringWidth($txt))/2;
		else
			$dx = $this->cMargin;
		if($this->ColorFlag)
			$s .= 'q '.$this->TextColor.' ';
		$s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',($this->CurX+$dx)*$k,($this->h-($this->CurY+0.5*$h+0.3*$this->FontSize))*$k,$this->_escape($txt));
		if($this->underline)
			$s .= ' '.$this->_dounderline($this->CurX+$dx,$this->CurY+0.5*$h+0.3*$this->FontSize,$txt);
		if($this->ColorFlag)
			$s .= ' Q';
		if($link)
			$this->Link($this->CurX+$dx,$this->CurY+0.5*$h-0.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
	}
	if($s)
		$this->_out($s);
	$this->lasth = $h;
	if($ln>0)
	{
		// Go to next line
		$this->CurY += $h;
		if($ln==1)
			$this->CurX = $this->lMargin;
	}
	else
		$this->CurX += $w;
}

function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
{
	// Output text with line breaks
	if(!isset($this->CurrentFont))
		$this->Error('No font has been set');
	$cw = &$this->CurrentFont['cw'];
	if($w==0)
		$w = $this->w-$this->rMargin-$this->CurX;
	$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
	$s = str_replace("\r",'',(string)$txt);
	$nb = strlen($s);
	if($nb>0 && $s[$nb-1]=="\n")
		$nb--;
	$b = 0;
	if($border)
	{
		if($border==1)
		{
			$border = 'LTRB';
			$b = 'LRT';
			$b2 = 'LR';
		}
		else
		{
			$b2 = '';
			if(strpos($border,'L')!==false)
				$b2 .= 'L';
			if(strpos($border,'R')!==false)
				$b2 .= 'R';
			$b = (strpos($border,'T')!==false) ? $b2.'T' : $b2;
		}
	}
	$sep = -1;
	$i = 0;
	$j = 0;
	$l = 0;
	$ns = 0;
	$nl = 1;
	while($i<$nb)
	{
		// Get next character
		$c = $s[$i];
		if($c=="\n")
		{
			// Explicit line break
			if($this->ws>0)
			{
				$this->ws = 0;
				$this->_out('0 Tw');
			}
			$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
			$i++;
			$sep = -1;
			$j = $i;
			$l = 0;
			$ns = 0;
			$nl++;
			if($border && $nl==2)
				$b = $b2;
			continue;
		}
		if($c==' ')
		{
			$sep = $i;
			$ls = $l;
			$ns++;
		}
		$l += $cw[$c];
		if($l>$wmax)
		{
			// Automatic line break
			if($sep==-1)
			{
				if($i==$j)
					$i++;
				if($this->ws>0)
				{
					$this->ws = 0;
					$this->_out('0 Tw');
				}
				$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
			}
			else
			{
				if($align=='J')
				{
					$this->ws = ($ns>1) ? ($wmax-$ls)/1000*$this->FontSize/($ns-1) : 0;
					$this->_out(sprintf('%.3F Tw',$this->ws*$this->k));
				}
				$this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
				$i = $sep+1;
			}
			$sep = -1;
			$j = $i;
			$l = 0;
			$ns = 0;
			$nl++;
			if($border && $nl==2)
				$b = $b2;
		}
		else
			$i++;
	}
	// Last chunk
	if($this->ws>0)
	{
		$this->ws = 0;
		$this->_out('0 Tw');
	}
	if($border && strpos($border,'B')!==false)
		$b .= 'B';
	$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
	$this->CurX = $this->lMargin;
}

function Write($h, $txt, $link='')
{
	// Output text in flowing mode
	if(!isset($this->CurrentFont))
		$this->Error('No font has been set');
	$cw = &$this->CurrentFont['cw'];
	$w = $this->w-$this->rMargin-$this->CurX;
	$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
	$s = str_replace("\r",'',(string)$txt);
	$nb = strlen($s);
	$sep = -1;
	$i = 0;
	$j = 0;
	$l = 0;
	$nl = 1;
	while($i<$nb)
	{
		// Get next character
		$c = $s[$i];
		if($c=="\n")
		{
			// Explicit line break
			$this->Cell($this->CurX-$this->lMargin,$h,substr($s,$j,$i-$j),0,2,'',false,$link);
			$i++;
			$sep = -1;
			$j = $i;
			$l = 0;
			if($nl==1)
			{
				$this->CurX = $this->lMargin;
				$w = $this->w-$this->rMargin-$this->CurX;
				$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
			}
			$nl++;
			continue;
		}
		if($c==' ')
			$sep = $i;
		$l += $cw[$c];
		if($l>$wmax)
		{
			// Automatic line break
			if($sep==-1)
			{
				if($this->CurX>$this->lMargin)
				{
					// Move to next line
					$this->CurX = $this->lMargin;
					$this->CurY += $h;
					$w = $this->w-$this->rMargin-$this->CurX;
					$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
					$i++;
					$nl++;
					continue;
				}
				if($i==$j)
					$i++;
				$this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',false,$link);
			}
			else
			{
				$this->Cell($w,$h,substr($s,$j,$sep-$j),0,2,'',false,$link);
				$i = $sep+1;
			}
			$sep = -1;
			$j = $i;
			$l = 0;
			if($nl==1)
			{
				$this->CurX = $this->lMargin;
				$w = $this->w-$this->rMargin-$this->CurX;
				$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
			}
			$nl++;
		}
		else
			$i++;
	}
	// Last chunk
	if($i!=$j)
		$this->Cell($this->GetStringWidth(substr($s,$j)),$h,substr($s,$j),0,0,'',false,$link);
}

function Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='')
{
	// Put an image on the page
}

function Ln($h=null)
{
	// Line feed; default value is last cell height
	$this->CurX = $this->lMargin;
	if($h===null)
		$this->CurY += $this->lasth;
	else
		$this->CurY += $h;
}

function GetX()
{
	// Get x position
	return $this->CurX;
}

function SetX($x)
{
	// Set x position
	if($x>=0)
		$this->CurX = $x;
	else
		$this->CurX = $this->w+$x;
}

function GetY()
{
	// Get y position
	return $this->CurY;
}

function SetY($y, $resetX=true)
{
	// Set y position and optionally reset x
	if($y>=0)
		$this->CurY = $y;
	else
		$this->CurY = $this->h+$y;
	if($resetX)
		$this->CurX = $this->lMargin;
}

function SetXY($x, $y)
{
	// Set x and y positions
	$this->SetY($y,false);
	$this->SetX($x);
}

function Output($dest='', $name='', $isUTF8=false)
{
	// Output PDF to some destination
	$this->Close();
	if(strlen($name)==0)
		$name = 'doc.pdf';
	if(strlen($dest)==0)
		$dest = 'I';
	switch(strtoupper($dest))
	{
		case 'I':
			// Send to standard output
			$this->_checkoutput();
			if(PHP_SAPI!='cli')
			{
				header('Content-Type: application/pdf');
				header('Content-Disposition: inline; filename="'.$name.'"');
				header('Cache-Control: private, max-age=0, must-revalidate');
				header('Pragma: public');
			}
			echo $this->buffer;
			break;
		case 'D':
			// Download file
			$this->_checkoutput();
			header('Content-Type: application/x-download');
			header('Content-Disposition: attachment; filename="'.$name.'"');
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
			echo $this->buffer;
			break;
		case 'F':
			// Save to local file
			$f = fopen($name,'wb');
			if(!$f)
				$this->Error('Unable to create output file: '.$name);
			fwrite($f,$this->buffer,strlen($this->buffer));
			fclose($f);
			break;
		case 'S':
			// Return as a string
			return $this->buffer;
		default:
			$this->Error('Incorrect output destination: '.$dest);
	}
	return '';
}

/*******************************************************************************
*                              Protected methods                               *
*******************************************************************************/

protected function _dochecks()
{
	// Check availability of %font subdirectory
	if(sprintf('%.1f',1.0)!='1.0')
		$this->Error('The current locale invalidates numeric operations. Please use setlocale(LC_NUMERIC, "C").');
}

protected function _checkoutput()
 	{
 		if(PHP_SAPI!='cli')
 		{
 			if(headers_sent($file,$line))
 			{
 				// Log error but don't crash if possible
 				// $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
 			}
 		}
	if(ob_get_length())
	{
		// The buffer must be silent or contain only a BOM
		if(preg_match('/^(\xEF\xBB\xBF)?\s*$/',ob_get_contents()))
 		{
 			ob_clean();
 		}
 		// Suppress error and clean anyway to allow PDF generation if possible
 		else
 		{
 			ob_clean();
 		}
	}
}

protected function _getpagesize($size)
{
	if(is_string($size))
	{
		$size = strtolower($size);
		if(!isset($this->StdPageSize[$size]))
			$this->Error('Unknown page size: '.$size);
		$a = $this->StdPageSize[$size];
		return array($a[0], $a[1]);
	}
	else
	{
		if($size[0]>$size[1])
			return array($size[1]*$this->k, $size[0]*$this->k);
		else
			return array($size[0]*$this->k, $size[1]*$this->k);
	}
}

protected function _beginpage($orientation, $size, $rotation)
{
	$this->page++;
	$this->pages[$this->page] = '';
	$this->PageSizes[$this->page] = array($this->wPt, $this->hPt);
	$this->state = 2;
	$this->CurX = $this->lMargin;
	$this->CurY = $this->tMargin;
	$this->FontFamily = '';
	// Check page size and orientation
	if($orientation=='')
		$orientation = $this->DefOrientation;
	else
		$orientation = strtoupper($orientation[0]);
	if($size=='')
		$size = $this->DefPageSize;
	else
		$size = $this->_getpagesize($size);
	if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1])
	{
		// New size or orientation
		if($orientation=='P')
		{
			$this->w = $size[0]/$this->k;
			$this->h = $size[1]/$this->k;
		}
		else
		{
			$this->w = $size[1]/$this->k;
			$this->h = $size[0]/$this->k;
		}
		$this->wPt = $this->w*$this->k;
		$this->hPt = $this->h*$this->k;
		$this->PageBreakTrigger = $this->h-$this->bMargin;
		$this->CurOrientation = $orientation;
		$this->CurPageSize = $size;
	}
}

protected function _endpage()
{
	$this->state = 1;
}

protected function _put($s)
 	{
 		$this->buffer .= $s."\n";
 	}
 
 	protected function _out($s)
 	{
 		if($this->state==2)
 			$this->pages[$this->page] .= $s."\n";
 		elseif($this->state==1)
 			$this->_put($s);
 		elseif($this->state==0)
 			$this->Error('No page has been added yet');
 		elseif($this->state==3)
 			$this->Error('The document is closed');
 	}

protected function _getoffset()
{
	return strlen($this->buffer);
}

protected function _newobj($n=null)
{
	// Begin a new object
	if($n===null)
		$n = ++$this->n;
	$this->offsets[$n] = $this->_getoffset();
	$this->_put($n.' 0 obj');
	return $n;
}

protected function _putheader()
{
	$this->_put('%PDF-'.$this->PDFVersion);
}

protected function _putobjs()
 	{
 		if(method_exists($this, '_putencryption'))
 			$this->_putencryption();
 		$this->_putfonts();
 		$this->_putimages();
 		// Resource dictionary
 		$this->_newobj();
 		$this->res_obj_id = $this->n;
 		$this->_put('<<');
 		$this->_put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
 		$this->_put('/Font <<');
 		foreach($this->fonts as $font)
 			$this->_put('/F'.$font['i'].' '.$font['n'].' 0 R');
 		$this->_put('>>');
 		$this->_put('/XObject <<');
 		foreach($this->images as $image)
 			$this->_put('/I'.$image['i'].' '.$image['n'].' 0 R');
 		$this->_put('>>');
 		$this->_put('>>');
 		$this->_put('endobj');
 		// Pages root
 		$this->PageRoot = $this->n + 1;
 		$this->n++;
 		// Page objects
 		$pages = array();
 		for($n=1;$n<=$this->page;$n++)
 		{
 			$this->_newobj();
 			$pages[$n] = $this->n;
 			if($n==1)
 				$this->first_page_id = $this->n;
 			$this->_put('<<');
 			$this->_put('/Type /Page');
 			$this->_put('/Parent '.$this->PageRoot.' 0 R');
 			$this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageSizes[$n][0],$this->PageSizes[$n][1]));
 			$this->_put('/Resources '.$this->res_obj_id.' 0 R');
 			$this->_put('/Contents '.($this->n+1).' 0 R');
 			$this->_put('>>');
 			$this->_put('endobj');
 			// Page content
 			$p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
 			$this->_newobj();
 			$this->_put('<<'.($this->compress ? '/Filter /FlateDecode ' : '').'/Length '.strlen($p).' >>');
 			$this->_putstream($p);
 			$this->_put('endobj');
 		}
 		// Pages root
 		$this->offsets[$this->PageRoot] = strlen($this->buffer);
 		$this->_put($this->PageRoot.' 0 obj');
 		$this->_put('<<');
 		$this->_put('/Type /Pages');
 		$kids = '/Kids [';
 		for($n=1;$n<=$this->page;$n++)
 			$kids .= $pages[$n].' 0 R ';
 		$this->_put($kids.']');
 		$this->_put('/Count '.$this->page);
 		$this->_put('>>');
 		$this->_put('endobj');
 	}

protected function _putfonts()
{
	$nf = $this->n;
	foreach($this->diffs as $diff)
	{
		// Encodings
		$this->_newobj();
		$this->_put('<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['.$diff.'] >>');
		$this->_put('endobj');
	}
	foreach($this->FontFiles as $file=>$info)
	{
		// Font file embedding
	}
	foreach($this->fonts as $k=>&$font)
	{
		// Font objects
		$font['n'] = $this->n+1;
		$type = $font['type'];
		$name = $font['name'];
		if($type=='core')
		{
			// Core font
			$this->_newobj();
			$this->_put('<< /Type /Font /BaseFont /'.$name.' /Subtype /Type1');
			if($name!='Symbol' && $name!='ZapfDingbats')
				$this->_put('/Encoding /WinAnsiEncoding');
			$this->_put('>>');
			$this->_put('endobj');
		}
		elseif($type=='Type1' || $type=='TrueType')
		{
			// Additional font
		}
		else
		{
			// User font
		}
	}
}

protected function _putimages()
{
}

protected function _putstream($s)
 	{
 		$this->_put('stream');
 		$this->_put($s);
 		$this->_put('endstream');
 	}

protected function _puttrailer()
 	{
 		$this->_put('/Size '.($this->n+1));
 		$this->_put('/Root '.$this->n.' 0 R');
 		$this->_put('/Info '.($this->n-1).' 0 R');
 		if(method_exists($this, '_puttrailer_encryption'))
 			$this->_puttrailer_encryption();
 	}
 
 	protected function _puttrailer_encryption()
 	{
 		// To be overridden by FPDF_Protection
 	}

protected function _enddoc()
{
	$this->_putheader();
	$this->_putobjs();
	// Info
	$this->_newobj();
	$this->_put('<<');
	$this->_put('/Producer '.$this->_textstring('FPDF '.FPDF_VERSION));
	if(!empty($this->title))
		$this->_put('/Title '.$this->_textstring($this->title));
	if(!empty($this->subject))
		$this->_put('/Subject '.$this->_textstring($this->subject));
	if(!empty($this->author))
		$this->_put('/Author '.$this->_textstring($this->author));
	if(!empty($this->keywords))
		$this->_put('/Keywords '.$this->_textstring($this->keywords));
	if(!empty($this->creator))
		$this->_put('/Creator '.$this->_textstring($this->creator));
	$this->_put('/CreationDate '.$this->_textstring('D:'.@date('YmdHis')));
	$this->_put('>>');
	$this->_put('endobj');
	// Catalog
 	$this->_newobj();
 	$this->_put('<<');
 	$this->_put('/Type /Catalog');
 	$this->_put('/Pages '.$this->PageRoot.' 0 R');
	if($this->ZoomMode=='fullpage')
 		$this->_put('/OpenAction ['.$this->first_page_id.' 0 R /Fit]');
 	elseif($this->ZoomMode=='fullwidth')
 		$this->_put('/OpenAction ['.$this->first_page_id.' 0 R /FitH null]');
 	elseif($this->ZoomMode=='real')
 		$this->_put('/OpenAction ['.$this->first_page_id.' 0 R /XYZ null null 1]');
 	elseif(!is_string($this->ZoomMode))
 		$this->_put('/OpenAction ['.$this->first_page_id.' 0 R /XYZ null null '.sprintf('%.2F',$this->ZoomMode/100).']');
	if($this->LayoutMode=='continuous')
		$this->_put('/PageLayout /OneColumn');
	elseif($this->LayoutMode=='two')
		$this->_put('/PageLayout /TwoColumnLeft');
	$this->_put('>>');
	$this->_put('endobj');
	// Cross-ref
	$o = strlen($this->buffer);
	$this->_put('xref');
	$this->_put('0 '.($this->n+1));
	$this->_put('0000000000 65535 f ');
	for($i=1;$i<=$this->n;$i++)
 	{
 		$offset = isset($this->offsets[$i]) ? $this->offsets[$i] : 0;
 		$this->_put(sprintf('%010d 00000 n ',$offset));
 	}
	// Trailer
	$this->_put('trailer');
	$this->_puttrailer();
	$this->_put('startxref');
	$this->_put($o);
	$this->_put('%%EOF');
	$this->state = 3;
}

protected function _escape($s)
{
	// Escape special characters in strings
	$s = str_replace('\\','\\\\',$s);
	$s = str_replace('(','\\(',$s);
	$s = str_replace(')','\\)',$s);
	$s = str_replace("\r",'\\r',$s);
	return $s;
}


protected function _textstring($s)
 	{
 		// Format a text string
 		return '('.$this->_escape($s).')';
 	}
 
 	protected function _dounderline($x, $y, $txt)
{
	// Underline text
	$up = $this->CurrentFont['up'];
	$ut = $this->CurrentFont['ut'];
	$w = $this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
	return sprintf('%.2F %.2F %.2F %.2F re f',$x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,$w*$this->k,-$ut/1000*$this->FontSizePt);
}
}
?>