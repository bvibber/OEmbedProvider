<?php
/**
 * API for MediaWiki 1.8+
 *
 * Created on Oct 13, 2006
 *
 * Copyright © 2006 Yuri Astrakhan <Firstname><Lastname>@gmail.com
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
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	// Eclipse helper - will be ignored in production
	require_once( "ApiBase.php" );
}

/**
 * @ingroup API
 */
class ApiOEmbed extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

	public function getCustomPrinter() {
		return $this->getMain()->createPrinterByName( 'json' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$url = $params['url'];
		$maxwidth = $params['maxwidth'];
		$maxheight = $params['maxheight'];

		$data = $this->getOEmbedData( $url, $maxwidth, $maxheight );

		$result = $this->getResult();
		foreach( $data as $key => $val ) {
			$result->addValue( null, $key, $val );
		}
	}

	function getOEmbedData( $url, $maxwidth=null, $maxheight=null ) {
		$target = $this->getOEmbedTarget($url);
		$data = array(
			'provider_name' => 'MediaWiki',
			'version' => '1.0',
		);
		if( $target->getNamespace() == NS_FILE ) {
			$file = wfFindFile( $target );
			$type = $file->getMediaType();
		} else {
			$file = null;
			$type = MEDIATYPE_UNKNOWN;
		}
		if( $type == MEDIATYPE_BITMAP || $type == MEDIATYPE_DRAWING ) {
			$data['type'] = 'photo';
			$data['width'] = $file->getWidth();
			$data['height'] = $file->getHeight();
			$data['url'] = wfExpandUrl( $file->getViewUrl() );
		} elseif ( $type == MEDIATYPE_VIDEO ) {
			$data['type'] = 'video';
			$data['width'] = $file->getWidth();
			$data['height'] = $file->getHeight();
			$data['html'] = 'inline media player here';
		} else {
			$data['type'] = 'link';
			$data['html'] = 'blah';
			$data['title'] = $target->getPrefixedText();
		}
		return $data;
	}

	/**
	 * Determine what page title is referenced by the given URL.
	 *
	 * @param string $url
	 * @return Title
	 */
	function getOEmbedTarget( $url ) {
		global $wgServer, $wgScript, $wgArticlePath;

		$target = null;
		$followRedirects = true;

		$articleRegex = '/^' . str_replace( '\$1', '(.*)', preg_quote( $wgServer . $wgArticlePath, '/' ) ) . '(?:\?|$)/';
		$scriptRegex = '/^' . preg_quote( $wgServer . $wgScript, '/' ) . '(?:\?|$)/';
		$matches = array();

		if( preg_match( $articleRegex, $url, $matches ) ) {
			// Standard article matchy thing.
			$target = urldecode( $matches[1] );
		} elseif( preg_match( $scriptRegex, $url ) ) {
			// Raw script hit; we'll check the title parameter!
			$parts = wfParseUrl( $url );
			if( !empty( $parts['query'] ) ) {
				$params = wfCgiToArray( $parts['query'] );
				if ( isset( $params['title'] ) ) {
					$target = $params['title'];
				}
				if ( isset( $params['redirect'] ) && $params['redirect'] == 'no') {
					$followRedirects = false;
				}
			}
		} else {
			// Not recognized.
			return null;
		}

		$title = Title::newFromText( $target );
		if( $title && $followRedirects ) {
			$article = new Article( $title );
			$redir = $article->getRedirectTarget();
			if( $redir ) {
				return $redir;
			}
		}
		return $title;
	}

	public function getAllowedParams() {
		return array(
			'url' => null,
			'maxwidth' => array(
				ApiBase::PARAM_DFLT => null,
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1
			),
			'maxheight' => array(
				ApiBase::PARAM_DFLT => null,
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1
			),
		);
	}

	public function getParamDescription() {
		return array(
			'url' => 'URL to resource to return data for',
			'maxwidth' => 'Maximum width in pixels for thumbnail or inline player',
			'maxwidth' => 'Maximum height in pixels for thumbnail or inline player',
		);
	}

	public function getDescription() {
		return 'This module implements the oEmbed protocol';
	}

	protected function getExamples() {
		$url = Title::newMainPage()->getFullUrl();
		return array(
			'api.php?action=oembed&url=' . urlencode($url)
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
