<?php
/**
 * @author Scrutinizer Auto-Fixer <auto-fixer@scrutinizer-ci.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\DAV\CardDAV\Sharing;

use OCA\DAV\Connector\Sabre\Auth;
use OCP\IRequest;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\XMLUtil;
use Sabre\DAVACL\IACL;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class Plugin extends ServerPlugin {

	const NS_OWNCLOUD = 'http://owncloud.org/ns';

	/** @var Auth */
	private $auth;

	/** @var IRequest */
	private $request;

	/**
	 * Plugin constructor.
	 *
	 * @param Auth $authBackEnd
	 * @param IRequest $request
	 */
	public function __construct(Auth $authBackEnd, IRequest $request) {
		$this->auth = $authBackEnd;
		$this->request = $request;
	}

	/**
	 * Reference to SabreDAV server object.
	 *
	 * @var \Sabre\DAV\Server
	 */
	protected $server;

	/**
	 * This method should return a list of server-features.
	 *
	 * This is for example 'versioning' and is added to the DAV: header
	 * in an OPTIONS response.
	 *
	 * @return string[]
	 */
	function getFeatures() {

		return ['oc-addressbook-sharing'];

	}

	/**
	 * Returns a plugin name.
	 *
	 * Using this name other plugins will be able to access other plugins
	 * using Sabre\DAV\Server::getPlugin
	 *
	 * @return string
	 */
	function getPluginName() {

		return 'carddav-sharing';

	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param Server $server
	 * @return void
	 */
	function initialize(Server $server) {
		$this->server = $server;
		$server->resourceTypeMapping['OCA\\DAV\CardDAV\\ISharedAddressbook'] = '{' . \Sabre\CardDAV\Plugin::NS_CARDDAV . '}shared';
		$this->server->xml->elementMap['{' . Plugin::NS_OWNCLOUD . '}share'] = 'OCA\\DAV\\CardDAV\\Sharing\\Xml\\ShareRequest';

		$this->server->on('method:POST', [$this, 'httpPost']);
	}

	/**
	 * We intercept this to handle POST requests on calendars.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return null|false
	 */
	function httpPost(RequestInterface $request, ResponseInterface $response) {

		$path = $request->getPath();

		// Only handling xml
		$contentType = $request->getHeader('Content-Type');
		if (strpos($contentType, 'application/xml') === false && strpos($contentType, 'text/xml') === false)
			return;

		// Making sure the node exists
		try {
			$node = $this->server->tree->getNodeForPath($path);
		} catch (NotFound $e) {
			return;
		}

		// CSRF protection
		$this->protectAgainstCSRF();

		$requestBody = $request->getBodyAsString();

		// If this request handler could not deal with this POST request, it
		// will return 'null' and other plugins get a chance to handle the
		// request.
		//
		// However, we already requested the full body. This is a problem,
		// because a body can only be read once. This is why we preemptively
		// re-populated the request body with the existing data.
		$request->setBody($requestBody);

		$message = $this->server->xml->parse($requestBody, $request->getUrl(), $documentType);

		switch ($documentType) {

			// Dealing with the 'share' document, which modified invitees on a
			// calendar.
			case '{' . self::NS_OWNCLOUD . '}share' :

				// We can only deal with IShareableCalendar objects
				if (!$node instanceof IShareableAddressBook) {
					return;
				}

				$this->server->transactionType = 'post-oc-addressbook-share';

				// Getting ACL info
				$acl = $this->server->getPlugin('acl');

				// If there's no ACL support, we allow everything
				if ($acl) {
					/** @var \Sabre\DAVACL\Plugin $acl */
					$acl->checkPrivileges($path, '{DAV:}write');
				}

				$node->updateShares($message->set, $message->remove);

				$response->setStatus(200);
				// Adding this because sending a response body may cause issues,
				// and I wanted some type of indicator the response was handled.
				$response->setHeader('X-Sabre-Status', 'everything-went-well');

				// Breaking the event chain
				return false;
		}
	}

	private function protectAgainstCSRF() {
		$user = $this->auth->getCurrentUser();
		if ($this->auth->isDavAuthenticated($user)) {
			return true;
		}

		if ($this->request->passesCSRFCheck()) {
			return true;
		}

		throw new BadRequest();
	}


}