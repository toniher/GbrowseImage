<?php

/**
 *	gbrowse_img.php  	           2010-07-19   Daniel Renfro <bluecurio@gmail.com>
 *
 *  Modified into parser function 	2013-2015 	Toni Hermoso Pulido (Toniher)
 */

if ( !defined( 'MEDIAWIKI' ) ) {
   die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

//self executing anonymous function to prevent global scope assumptions
call_user_func( function() {

	$GLOBALS['wgExtensionCredits']['parserhook'][] = array(
	  'author'      => array('[mailto:bluecurio@gmail.com Daniel Renfro]', 'Jim Hu', 'Toni Hermoso'),
	  'description' => 'gbrowse images',
	  'name'        => 'GrowseImage',
	  'update'      => '2015-02-09',
	  'url'         => 'https://www.mediawiki.org/wiki/Extension:GbrowseImage',
	  'type'        => 'hook',
	  'version'     => '0.3'
	);

	# A var to ease the referencing of files
	$dir = dirname(__FILE__) . '/';


	$GLOBALS['gbrowse_url'] = "http://heptamer.tamu.edu/fgb2/";

	$GLOBALS['wgHooks']['ParserFirstCallInit'][] = 'efGbrowseImageSetup';
	$GLOBALS['wgExtensionMessagesFiles']['GbrowseImageMagic'] = $dir . '/GbrowseImage.i18n.magic.php';

});


// hookup the object/method to the tag
function efGbrowseImageSetup ( $parser ) {
	$parser->setFunctionHook( "gbrowseImage", 'GbrowseImage::execute' );
	return true;
}



class GbrowseImage {

	// create a constant to pad the coordinates
	const PADDING_LENGTH = 1000;

	// various settings
	private static $url;					// URL of GBrowser
	private static $preset;       				// used to distinguish between different types of preset configuations (presets)
	private static $caption;					// wikitext to use as a caption (probably a transclusion)
	private static $query = array();			// will hold all the options for gbrowse_img, assoc. array

	// gbrowse_img arguments
	private static $source;					// the source of the data, never null
	private static $name;						// genomic landmark or range
	private static $coordinates;    			// an array, order dependent, possibly null
	private static $type;						// tracks to include in image
	private static $width; 				    // desired width of image
	private static $height; 				    // desired height of image
	private static $options;					// list of track options (compact, labeled, etc)
	private static $abs;						// display position in absolute coordinates
	private static $add;						// added feature(s) to superimpose on the image
	private static $style;						// stylesheet for additional features
	private static $keystyle;					// where to place the image key
	private static $overview;					// force an overview-style display
	private static $flip;              		// bool, whether to reverse the image or not
	private static $grid;						// turn grid on (1) or off (0)
	private static $embed;						// generate full HTML for image and imagemap for use in an embedded frame
	private static $format;					// format for the image (use "SVG" for scaleable vector graphics)

	

	public static function execute ( $parser, $frame, $args ) {

		$params = func_get_args();
		array_shift( $params ); // We already know the $parser ...
		
		self::parseInput( $parser, $params );

		$endval = self::makeLink();
		
		return array( $endval, 'noparse' => true, 'isHTML' => true );

	}


	protected function parseInput(  &$parser, $params  ) {
		
		global $gbrowse_url;
		self::$url = $gbrowse_url;
		
		$positionalParameters = false;

		foreach ( $params as $i => $param ) {
	
		
			$elements = explode('=', $param, 2 );
	
			// set param_name and value
			if ( count( $elements ) > 1 && !$positionalParameters ) {
				$param_name = trim( $elements[0] );
				// parse (and sanitize) parameter values
				$value = trim( $parser->recursiveTagParse( $elements[1] ) );
			} else {
				$param_name = null;
	
				// parse (and sanitize) parameter values
				$value = trim( $parser->recursiveTagParse( $param ) );
			}
	
			switch ( $param_name ) {
				case "url":
					self::$url = $value;
					break;
				case "source":
					self::$source = $value;
					break;
				case "name":
					self::$name = $value;
					break;
				case "width":
					// get the integer value of whatever came in
					self::$width = intval( $value );
					break;
				case "height":
					// get the integer value of whatever came in
					self::$height = intval( $value );
					break;
				case "type":
					self::$type = $value;
					break;
				case 'options':
					self::$options = $value;
					break;
				case 'abs':
					self::$abs = $value;
					break;
				case 'add':
					self::$add = $value;
					break;
				case 'style':
					self::$style = $value;
					break;
				case 'keystyle':
					self::$keystyle = $value;
					break;
				case 'overview':
					self::$overview = $value;
					break;
				case 'grid':
					self::$grid = $value;
					break;
				case 'format':
					self::$format = $value;
					break;
				case "embed":
					self::$embed = $value;
					break;
				case "flip":
					// if there is anything that evaluates to true, use it
					if ( $value ) {
						self::$flip = true;
					}
					break;
				case "preset":
					self::$preset = $value;
					break;
				case 'caption':
					// could be problematic because of multiple lines....oh well.
					self::$caption = $value;
					break;
			}
		}
		
	}

	public function makeLink() {

		wfProfileIn( __METHOD__ );

		// check for required parameters
		if ( !isSet(self::$source) ) {
			trigger_error( 'no source set, cannot continue', E_USER_WARNING );
			return self::makeErrorString( 'No \'source\' parameter set. Cannot make image.' );
		}
		if ( !isSet(self::$name) ) {
			trigger_error( 'no name set, cannot continue', E_USER_WARNING );
			return self::makeErrorString( 'No \'name\' parameter set. Cannot make image.' );
		}

		// set up the basic/default options for gbrowse_img into an array
		self::$query['name'] = self::$name;
				
		self::$query['width'] 	= ( isSet(self::$width) && self::$width )
			? self::$width
			: 300;
		self::$query['type'] 	= ( isSet( self::$type) && self::$type )
			? self::$type
			: "Genes+Genes:region+ncRNA+ncRNA:region";

		if ( isSet(self::$options) ) {
			self::$query['options'] = self::$options;
		}
		if ( isSet(self::$abs) ) {
			self::$query['abs'] = self::$abs;
		}
		if ( isSet(self::$add) ) {
			self::$query['add'] = self::$add;
		}
		if ( isSet(self::$style) ) {
			self::$query['style'] = self::$style;
		}
		if ( isSet(self::$keystyle) ) {
			self::$query['keystyle'] = self::$keystyle;
		}
		if ( isSet(self::$flip) ) {
			self::$query['flip'] = self::$flip;
		}
		if ( isSet(self::$grid) ) {
			self::$query['grid'] = self::$grid;
		}
		if ( isSet(self::$embed) ) {
			self::$query['embed'] = self::$embed;
		}
		if ( isSet(self::$format) ) {
			self::$query['format'] = self::$format;
		}

		//  check if we're serving up a preset, overwrite any settings with these
		if ( isSet(self::$preset) && self::$preset ) {
			switch ( self::$preset ) {
			    case "GeneLocation":
			    	// pad the figure with a set amount on 5' and 3' ends
			    	$padding_amount = 1000; //  1kb nt
			 		list($landmark, $coordA, $coordB) = self::parseLandmark( self::$name );
					// don't go further than the origin on the 5' side
					if ( $coordA - $padding_amount < 0 ) {
						$coordA = 0;
					}
					else {
						$coordA -= $padding_amount;
					}
					$coordB += $padding_amount;
					// reconstruct the name parameter
					self::$query['name'] = sprintf('%s:%d..%d', $landmark, $coordA, $coordB );
			        break;
			    case "Nterminus":
			    	// we have to turn flip on/off explicitly in the parameters.
			    	// GBrowse allows low->high coordinates to be on the minus strand.
			    	// i.e. ( high->low != 'minus strand' )
			    	self::$query['name'] = self::$name;
			    	self::$query['type'] = 'Gene+DNA_+Protein';
			    	self::$query['width'] = 400;
			    	break;
			    case 'SubtilisQuickView_xy':
					// This is for the new quickview on the subtiliswiki
					self::$query['name'] = self::$name;
					self::$query['type'] =  'Rasmussen_xy';
					self::$query['wdith'] = 500;
					break;
				case 'SubtilisQuickView_xy_LB_genes':
					self::$query['name'] =self:: $name;
					self::$query['type'] =  'Genes+Rasmussen_xy_LB';
					self::$query['wdith'] = 500;
					break;
	            case 'SubtilisQuickView_density':
					// This is for the new quickview on the subtiliswiki
					self::$query['name'] = self::$name;
					self::$query['type'] =  'Rasmussen_density';
					self::$query['wdith'] = 500;
					break;
				 case 'SubtilisQuickView_genes':
					// This is for the new quickview on the subtiliswiki
					self::$query['name'] = self::$name;
					self::$query['type'] =  'Genes';
					self::$query['wdith'] = 500;
					break;
	            default:
			    	// do nothing.
			        break;
			}
		}		// make the HTML
		
		
		if ( isSet(self::$embed) && is_numeric(self::$embed) && self::$embed > 0 ) {
		
			$height = 250;
			if ( isSet(self::$height) && is_numeric(self::$height) && self::$height > 0 ) {
				$height = self::$height;
			}
			
			$width = self::$query['width'];
			
		
			$html = '<iframe src="'.self::makeGbrowseImgURL($embed=1).'" width="'.$width.'" height="'.$height.'" >
		            <img src="' . self::makeGbrowseImgURL() . '" alt="' . self::makeGbrowseURL() . '" />
		        </iframe>';
			
		}
		else {	
		
			$html = '<a href="' . self::makeGbrowseURL() . '" target="_blank">
		            <img src="' . self::makeGbrowseImgURL() . '" alt="' . self::makeGbrowseURL() . '" />
		        </a>';
		}
		
		// for debugging
		#$html .= '<br />' . htmlentities($this->makeGbrowseImgURL());

		$html .= ( isSet(self::$caption) && self::$caption )
			? "\n" . self::$caption
			: "";

		wfProfileOut( __METHOD__ );
		
		return $html . "\n";
	}
	
	
	
	// EcoliWiki's Gbrowse2 doesn't like to have plus-signs (+) in the type paramter in 
	//   the URL, so let's kludge it by adding multiple "&type="'s. 
	//
	// The http_build_query() function will add the first "type=", let's take care of the rest...
	// 
	protected function formatTypeParameter() {
		$tracks = explode( '+', self::$query['type'] );
		$string = "";
		for ( $i=0, $c=count($tracks); $i<$c; $i++ ) {
			if ( $i != 0 ) {
				$string .= '&type=';
			}
			$string .= $tracks[$i];
		}
		self::$query['type'] = $string;
			
	}
	


	// use like this:    list($chromosome, $coordA, $coordB) = $this->parseLandmark( $landmark );
	protected function parseLandmark( $name ) {
		if ( preg_match( '/(.*):(\d+)\.\.(\d+)/', $name, $m ) ) {
			return array_splice( $m, 1, 3 ); // return $m[1..3]
		}
		else {
			return false;
		}
	}

	protected function makeGbrowseImgURL($embed=0) {
		
		if ($embed = 0) {
			if ( array_key_exists ( "embed" , self::$query ) ) {
				unset(self::$query["embed"]);
			}
		}
		
		$base = self::$url.'gbrowse_img/'. self::$source;
		self::formatTypeParameter();
		$url = $base . '/?' . http_build_query( self::$query );
		return urldecode($url);
	}

	protected function makeGbrowseURL() {
		$base = self::$url. 'gbrowse/' . self::$source;
		$url = $base . '/?name=' . self::$name;
		return urldecode($url);
	}

	protected function makeErrorString( $message ) {
		return '<pre style="color:red;">gbrowseImage error:' . "\n  " . $message . '</pre>';
	}

}

