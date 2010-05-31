<?php

class Prest_Resource
{
	protected $_service = null;
	protected $_request = null;

	protected $_directory = null;
	protected $_action = null;

	protected $_response = null;

	protected $_params = null;

	protected $_media_types = null;
	protected $_representation = null;

################################################################################
# public
################################################################################

	public function __construct( array $i_params )
	{
		# set properties #######################################################

		$this->_service = $i_params['service'];
		$this->_request = $i_params['request'];

		$this->_directory = $i_params['directory'];
		$this->_action_type = $i_params['action_type'];
		$this->_action = $i_params['action'];

		$this->_setup();
		$this->_validate();
	}

	public function getDirectory() { return $this->_directory; }

	############################################################################
	# params ###################################################################

	public function getParam( $i_param )
	{
		if ( !$this->_params )
			$this->getParams();

		if ( isset($this->_params[$i_param]) )
			return $this->_params[$i_param];

		return null;
	}

	public function getParams()
	{
		if ( !$this->_params )
		{
			$matched_route = $this->_service->getRouter()->getMatchedRoute();

			$request_params = $this->_request->getParams();
			$route_params = $matched_route['params'];

			foreach ( $route_params as $rp => $v )
				$request_params[$rp] = $v;

			$this->_params = $request_params;
		}

		return $this->_params;
	}

	############################################################################

	public function getMediaTypes() { return $this->_media_types; }

	public function getRepresentation() { return $this->_representation; }

	############################################################################
	# execution ################################################################

	public function execute( $i_action = null )
	{
		$response = null;

		$action = $i_action ? $i_action : $this->_action;

		$response = $this->$action();
				
		return $response;
	}

################################################################################
# protected
################################################################################

	############################################################################
	# setup ####################################################################

	protected function _setup()
	{
		$this->_setupMediaTypes();
		//$this->_setupRepresentation();
	}

	protected function _setupMediaTypes()
	{
		$directory = "{$this->_directory}/representations/{$this->_action_type}";

		if ( is_dir($directory) )
		{
			$media_types = array();
			$d = new DirectoryIterator($directory);

			foreach ( $d as $item )
			{
				if ( $item->isDot() )
					continue;

				$file_name = $item->getFilename();

				if ( $item->isFile() and strpos($file_name, '.phtml') !== false )
				{
					$media_type = str_replace('_', '/', substr($file_name, 0, -6));

					$media_types[] = $media_type;
				}
			}

			$this->_media_types = $media_types;
		}
	}

	protected function _setupRepresentation()
	{
		$params = array
		(
			'service' => $this->_service,
			'request' => $this->_request,
			'resource' => $this
		);

		$this->_representation = new Prest_Representation($params);
	}

	############################################################################
	# validation ###############################################################

	protected function _validate()
	{
		$this->_checkAction();
		$this->_checkMediaType();
		$this->_checkLanguage();
	}

	protected function _checkAction()
	{
		if ( !method_exists($this, $this->_action) )
			throw new Prest_Exception(null, Prest_Response::NOT_FOUND);
	}

	protected function _checkMediaType()
	{
		$is_media_type_supported = false;

		$requested_media_types = $this->_request->getHeaders()->getAccept();

		foreach ( $requested_media_types as $requested_mt )
		{
			if ( strpos($requested_mt, '*') !== 'false' )
			{
				$pattern = str_replace('*', '.*', $requested_mt);
				$pattern = str_replace('/', '\/', $pattern);
				$pattern = "/^$pattern\$/u";

				foreach ( $this->_media_types as $supported_mt )
				{
					if ( preg_match($pattern, $supported_mt) === 1 )
					{
						$is_media_type_supported = true;
						break 2;
					}
				}
			}
			elseif ( in_array($requested_mt, $this->_media_types) )
			{
				$is_media_type_supported = true;
				
				break;
			}
		}

		if ( !$is_media_type_supported )
			throw new Prest_Exception(null, Prest_Response::UNSUPPORTED_MEDIA_TYPE);
	}

	protected function _checkLanguage()
	{
		$is_language_supported = false;

		$requested_languages = $this->_request->getHeaders()->getAcceptLanguage();

		foreach ( $requested_languages as $requested_l )
		{
			
		}
	}

	protected function _authenticate()
	{
		return true;
	}

	protected function _authorize()
	{
		return true;
	}

	############################################################################

	protected function _selectBestMediaType()
	{
		if ( !$this->_media_types )
			die('No media types are supported by this resource.');

		$requested = $this->_request->getHeaders()->getAccept();
		$default = $this->_service->getDefaultMediaType();

		$selected_media_type = null;

		foreach ( $this->_media_types as $media_type )
		{
			if ( in_array($media_type, $requested) )
			{
				$selected_media_type = $media_type;
				break;
			}
		}

		if ( !$selected_media_type )
		{
			echo 'media type not selected.';// TODO:
		}

		return $selected_media_type;
	}

	protected function _selectBestLanguage()
	{
		$supported = $this->_service->getSupportedLanguages();
		$requested = $this->_request->getHeaders()->getAcceptLanguage();
		$default = $this->_service->getDefaultLanguage();

		$selected_language = null;

		foreach ( $supported as $language )
		{
			if ( in_array($language, $requested) )
			{
				$selected_language = $language;
				break;
			}
		}

		if ( !$selected_language )
		{
			// TODO:
		}

		return $selected_language;
	}

	protected function _selectRepresentationTemplate( $i_media_type )
	{
		$selected_template = null;
		$route = $this->_service->getRouter()->getMatchedRoute();

		if ( $i_media_type )
		{
			$file_name = str_replace('/', '_', $i_media_type) . '.phtml';
			$template = "{$this->_directory}/representations/{$route['type']}/$file_name";

			if ( is_file($template) )
				$selected_template = $template;
		}

		return $selected_template;
	}
}

?>