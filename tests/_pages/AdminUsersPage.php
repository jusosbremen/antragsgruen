<?php

namespace app\tests\_pages;

use Helper\BasePage;

/**
 * @property \AcceptanceTester|\FunctionalTester $actor
 */
class AdminUsersPage extends BasePage
{
    public $route = 'admin/users/index';
}
