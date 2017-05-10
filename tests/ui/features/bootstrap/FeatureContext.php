<?php
/**
 * ownCloud
*
* @author Artur Neumann
* @copyright 2017 Artur Neumann info@individual-it.net
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\RawMinkContext;
use Page\OwncloudPage;
use Page\LoginPage;

require_once 'bootstrap.php';

/**
 * Features context.
 */
class FeatureContext extends RawMinkContext implements Context
{
	private $owncloudPage;
	private $loginPage;
	
	public function __construct(OwncloudPage $owncloudPage, LoginPage $loginPage)
	{
		$this->owncloudPage = $owncloudPage;
		$this->loginPage = $loginPage;
	}
	
	/** @BeforeScenario @AdminLogin*/
	public function setUpScenario()
	{
		$this->loginPage->open();
		$this->filesPage = $this->loginPage->loginAs("admin", "admin");
		$this->filesPage->waitTillPageIsloaded(10);
	}
	
	/**
	 * @Then a notification should be displayed with the text :notificationText
	 */
	public function aNotificationShouldBeDisplayedWithTheText($notificationText)
	{
		PHPUnit_Framework_Assert::assertEquals(
			$notificationText, $this->owncloudPage->getNotificationText()
		);
	}
	
	/** @BeforeScenario */
	public function setUpSuite(BeforeScenarioScope $scope)
	{
		$jobId = $this->getSessionId($scope);
		file_put_contents("/tmp/saucelabs_sessionid", $jobId);
	}
	
	public function getSessionId(BeforeScenarioScope $scope)
	{
		$url = $this->getSession()->getDriver()->getWebDriverSession()->getUrl();
		$parts = explode('/', $url);
		$sessionId = array_pop($parts);
		return $sessionId;
	}
}
