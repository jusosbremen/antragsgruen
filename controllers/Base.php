<?php

namespace app\controllers;

use app\models\exceptions\{ApiResponseException, NotFound, Internal};
use app\models\http\{ResponseInterface, RestApiExceptionResponse, RestApiResponse};
use app\components\{ConsultationAccessPassword, HTMLTools, RequestContext, UrlHelper};
use app\models\settings\{AntragsgruenApp, Layout};
use app\models\db\{Amendment, Consultation, ConsultationUserGroup, Motion, Site, User};
use Yii;
use yii\base\Module;
use yii\helpers\Html;
use yii\web\{Controller, Request, Response, Session};

class Base extends Controller
{
    public ?Layout $layoutParams = null;
    public ?Consultation $consultation = null;
    public ?Motion $motion = null;
    public ?Amendment $amendment = null;
    public ?Site $site = null;

    /** @var string */
    public $layout = '@app/views/layouts/column1';

    /** @var null|bool - currently only null (default) and true (allow not-logged in, e.g. by plugins) are supported. false to come. */
    public ?bool $allowNotLoggedIn = null;

    /**
     * @param string $cid the ID of this controller.
     * @param Module $module the module that this controller belongs to.
     * @param array $config name-value pairs that will be used to initialize the object properties.
     */
    public function __construct($cid, $module, $config = [])
    {
        parent::__construct($cid, $module, $config);

        // Hint: can be overwritten in loadConsultation
        $this->layoutParams = new Layout();
    }

    /**
     * @param \yii\base\Action $action
     * @throws Internal
     * @throws \Exception
     * @throws \yii\base\ExitException
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        Yii::$app->response->headers->add('X-Xss-Protection', '1');
        Yii::$app->response->headers->add('X-Content-Type-Options', 'nosniff');
        Yii::$app->response->headers->add('X-Frame-Options', 'sameorigin');

        if (!parent::beforeAction($action)) {
            return false;
        }

        $params = $this->getHttpRequest()->resolve();
        $appParams = AntragsgruenApp::getInstance();

        if ($appParams->updateKey) {
            $this->showErrorpage(503, Yii::t('base', 'err_update_mode'));
        }

        $inManager = (get_class($this) === ManagerController::class);
        $inInstaller = (get_class($this) === InstallationController::class);

        if ($appParams->siteSubdomain) {
            if (strpos($appParams->siteSubdomain, 'xn--') === 0) {
                HTMLTools::loadNetIdna2();
                $idna = new \Net_IDNA2();
                $subdomain = $idna->decode($appParams->siteSubdomain);
            } else {
                $subdomain = $appParams->siteSubdomain;
            }

            $consultation = $params[1]['consultationPath'] ?? '';
            if ($consultation === '' && $this->isGetSet('passConId')) {
                $consultation = $this->getHttpRequest()->get('passConId');
            }
            $this->loadConsultation($subdomain, $consultation);
            if ($this->site) {
                $this->layoutParams->setLayout($this->site->getSettings()->siteLayout);
            } else {
                $this->layoutParams->setLayout(Layout::getDefaultLayout());
            }
        } elseif (isset($params[1]['subdomain'])) {
            if (strpos($params[1]['subdomain'], 'xn--') === 0) {
                HTMLTools::loadNetIdna2();
                $idna = new \Net_IDNA2();
                $subdomain = $idna->decode($params[1]['subdomain']);
            } else {
                $subdomain = $params[1]['subdomain'];
            }

            $consultation = $params[1]['consultationPath'] ?? '';
            if ($consultation === '' && $this->isGetSet('passConId')) {
                $consultation = $this->getHttpRequest()->get('passConId');
            }
            $this->loadConsultation($subdomain, $consultation);
            if ($this->site) {
                $this->layoutParams->setLayout($this->site->getSettings()->siteLayout);
            } else {
                $this->layoutParams->setLayout(Layout::getDefaultLayout());
            }
        } elseif (!($inInstaller || $inManager) && !$appParams->multisiteMode) {
            $this->layoutParams->setLayout(Layout::getDefaultLayout());
            $this->showErrorpage(500, Yii::t('base', 'err_no_site_internal'));
        } else {
            $this->layoutParams->setLayout(Layout::getDefaultLayout());
        }

        if ($this->allowNotLoggedIn === true) {
            return true;
        }

        // If re-installing while being logged in in the old installation, the testConsultationPwd below would break the site
        if ($inInstaller) {
            return true;
        }

        if (get_class($this) === PagesController::class && in_array($action->id, ['show-page', 'css'])) {
            return true;
        }
        if (get_class($this) === PagesController::class && $action->id === 'file' && $this->consultation) {
            $logo = urldecode(basename($this->consultation->getSettings()->logoUrl));
            if ($logo && isset($params[1]) && isset($params[1]['filename']) && $params[1]['filename'] === $logo) {
                return true;
            }
            if (isset($this->site->getSettings()->getStylesheet()->backgroundImage)) {
                $bg = urldecode(basename($this->site->getSettings()->getStylesheet()->backgroundImage));
                if (isset($params[1]) && isset($params[1]['filename']) && $params[1]['filename'] === $bg) {
                    return true;
                }
            }
        }

        if ($this->testMaintenanceMode() || $this->testSiteForcedLogin() || $this->testConsultationPwd()) {
            return false;
        }
        return true;
    }

    public function runAction($id, $params = []): ?string
    {
        try {
            $response = parent::runAction($id, $params);
        /** @phpstan-ignore-next-line */
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (ApiResponseException $e) {
            $response = new RestApiExceptionResponse($e->getCode(), $e->getMessage());
            return $response->renderYii($this->layoutParams, $this->getHttpResponse());
        }

        if (is_string($response)) {
            return $response;
        }
        if (is_object($response) && is_a($response, ResponseInterface::class)) {
            /** @var ResponseInterface $response */
            return $response->renderYii($this->layoutParams, $this->getHttpResponse());
        }
        return $response;
    }

    /**
     * @param array|string $url
     * @param int $statusCode
     * @return mixed
     * @throws \yii\base\ExitException
     */
    public function redirect($url, $statusCode = 302)
    {
        $response = parent::redirect($url, $statusCode);
        Yii::$app->end();
        return $response;
    }

    protected function getHttpRequest(): Request
    {
        /** @var Request $request */
        $request = Yii::$app->request;
        return $request;
    }

    protected function getHttpResponse(): Response
    {
        /** @var Response $response */
        $response = Yii::$app->response;
        return $response;
    }

    protected function getHttpSession(): Session
    {
        return RequestContext::getSession();
    }

    protected function getHttpMethod(): string
    {
        return $this->getHttpRequest()->method;
    }

    protected function getHttpHeader(string $headerName): ?string
    {
        return $this->getHttpRequest()->headers->get($headerName);
    }

    protected function getPostBody(): string
    {
        return $this->getHttpRequest()->getRawBody();
    }

    protected function isPostSet(string $name): bool
    {
        $post = $this->getHttpRequest()->post();
        return isset($post[$name]);
    }

    protected function isGetSet(string $name): bool
    {
        $get = $this->getHttpRequest()->get();
        return isset($get[$name]);
    }

    public function isRequestSet(string $name): bool
    {
        return $this->isPostSet($name) || $this->isGetSet($name);
    }

    /**
     * @param string $name
     * @param null|mixed $default
     * @return mixed
     */
    public function getRequestValue($name, $default = null)
    {
        $post = $this->getHttpRequest()->post();
        if (isset($post[$name])) {
            return $post[$name];
        }
        $get = $this->getHttpRequest()->get();
        if (isset($get[$name])) {
            return $get[$name];
        }
        return $default;
    }

    /**
     * @param mixed|null $default
     * @return array|mixed|object
     */
    public function getPostValue(string $name, $default = null)
    {
        return $this->getHttpRequest()->post($name, $default);
    }

    public function renderContentPage(string $pageKey): string
    {
        if ($this->consultation) {
            $admin = User::havePrivilege($this->consultation, ConsultationUserGroup::PRIVILEGE_CONTENT_EDIT);
        } else {
            $user  = User::getCurrentUser();
            $admin = ($user && in_array($user->id, $this->getParams()->adminUserIds));
        }
        return $this->render(
            '@app/views/pages/contentpage',
            [
                'pageKey' => $pageKey,
                'admin'   => $admin,
            ]
        );
    }

    /**
     * @param string $view
     * @param array $options
     * @return string
     */
    public function render($view, $options = array())
    {
        $params = array_merge(
            [
                'consultation' => $this->consultation,
            ],
            $options
        );
        return parent::render($view, $params);
    }

    /**
     * @throws ApiResponseException
     */
    public function handleRestHeaders(array $allowedMethods, bool $alwaysEnabled = false): void
    {
        $this->layoutParams->setFallbackLayoutIfNotInitializedYet();
        $this->layoutParams->robotsNoindex = true;

        if (!$this->site->getSettings()->apiEnabled && !$alwaysEnabled && !User::getCurrentUser()) {
            throw new ApiResponseException('Public API disabled', 403);
        }
        if ($this->consultation && ($this->consultation->urlPath === null || $this->consultation->dateDeletion)) {
            throw new ApiResponseException('Consultation not found', 404);
        }
        if ($this->consultation && $this->consultation->getSettings()->maintenanceMode && !$alwaysEnabled) {
            if (!User::havePrivilege($this->consultation, ConsultationUserGroup::PRIVILEGE_CONSULTATION_SETTINGS)) {
                throw new ApiResponseException('Consultation in maintenance mode', 404);
            }
        }

        if ($this->site->getSettings()->apiCorsOrigins) {
            if (in_array('*', $this->site->getSettings()->apiCorsOrigins)) {
                Yii::$app->response->headers->add('Access-Control-Allow-Origin', '*');
            } elseif ($this->getHttpRequest()->origin && in_array($this->getHttpRequest()->origin, $this->site->getSettings()->apiCorsOrigins)) {
                Yii::$app->response->headers->add('Access-Control-Allow-Origin', $this->getHttpRequest()->origin);
            }
        }
        Yii::$app->response->headers->add('Access-Control-Allow-Methods', implode(', ', $allowedMethods));

        if ($this->getHttpMethod() === 'OPTIONS') {
            Yii::$app->end();
        }
        if (!in_array($this->getHttpMethod(), $allowedMethods)) {
            throw new ApiResponseException('Method not allowed', 405);
        }
    }

    public function returnRestResponseFromException(\Exception $exception): RestApiResponse {
        return new RestApiExceptionResponse($exception->getCode() > 0 ? $exception->getCode() : 500, $exception->getMessage());
    }

    public function getParams(): AntragsgruenApp
    {
        /** @var AntragsgruenApp $app */
        $app = Yii::$app->params;
        return $app;
    }


    /**
     * @throws \yii\base\ExitException
     */
    public function testMaintenanceMode(): bool
    {
        if ($this->consultation == null) {
            return false;
        }
        $settings = $this->consultation->getSettings();
        $admin    = User::havePrivilege($this->consultation, ConsultationUserGroup::PRIVILEGE_CONSULTATION_SETTINGS);
        if ($settings->maintenanceMode && !$admin) {
            $this->redirect(UrlHelper::createUrl(['/pages/show-page', 'pageSlug' => 'maintenance']));
            return true;
        }
        return false;
    }

    /**
     * @throws \yii\base\ExitException
     */
    public function testSiteForcedLogin(): bool
    {
        if ($this->consultation === null) {
            return false;
        }
        if (!$this->consultation->getSettings()->forceLogin) {
            return false;
        }
        if (RequestContext::getUser()->getIsGuest()) {
            $this->redirect(UrlHelper::createUrl(['/user/login', 'backUrl' => $_SERVER['REQUEST_URI']]));
            return true;
        }
        if ($this->consultation->getSettings()->managedUserAccounts) {
            if (count(User::getCurrentUser()->getUserGroupsForConsultation($this->consultation)) === 0) {
                $this->redirect(UrlHelper::createUrl('/user/consultationaccesserror', $this->consultation));
                return true;
            }
        }
        return false;
    }

    public function testConsultationPwd(): bool
    {
        if (!RequestContext::getUser()->getIsGuest()) {
            return false;
        }
        if (!$this->consultation || !$this->consultation->getSettings()->accessPwd) {
            return false;
        }
        $pwdChecker = new ConsultationAccessPassword($this->consultation);
        if (!$pwdChecker->isCookieLoggedIn()) {
            $loginUrl = UrlHelper::createUrl([
                '/user/login',
                'backUrl'   => $this->getHttpRequest()->url,
                'passConId' => $this->consultation->urlPath,
            ]);
            $this->redirect($loginUrl);
            Yii::$app->end();
            return true;
        } else {
            return false;
        }
    }

    public function forceLogin(): void
    {
        if (RequestContext::getUser()->getIsGuest()) {
            $loginUrl = UrlHelper::createUrl(['/user/login', 'backUrl' => $this->getHttpRequest()->url]);
            $this->redirect($loginUrl);
            Yii::$app->end();
        }
    }

    public function showErrors(bool $addBorder = false): string
    {
        $session = $this->getHttpSession();
        if (!$session->isActive) {
            return '';
        }
        $str = '';

        $error = $session->getFlash('error', null, true);
        if ($error) {
            $str .= '<div class="alert alert-danger" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                <span class="sr-only">' . Yii::t('base', 'aria_error') . ':</span>
                ' . nl2br(Html::encode($error)) . '
            </div>';
        }

        $success = $session->getFlash('success', null, true);
        if ($success) {
            $str .= '<div class="alert alert-success" role="alert">
                <span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
                <span class="sr-only">' . Yii::t('base', 'aria_success') . ':</span>
                ' . Html::encode($success) . '
            </div>';
        }

        $info = $session->getFlash('info', null, true);
        if ($info) {
            $str .= '<div class="alert alert-info" role="alert">
                <span class="glyphicon glyphicon glyphicon-info-sign" aria-hidden="true"></span>
                <span class="sr-only">' . Yii::t('base', 'aria_info') . ':</span>
                ' . Html::encode($info) . '
            </div>';
        }

        $email = $session->getFlash('email', null, true);
        if ($email && YII_ENV === 'test') {
            $str .= '<div class="alert alert-info" role="alert">
                <span class="glyphicon glyphicon glyphicon-info-sign" aria-hidden="true"></span>
                <span class="sr-only">' . Yii::t('base', 'aria_info') . ':</span>
                ' . Html::encode($email) . '
            </div>';
        }

        if ($str !== '' && $addBorder) {
            $str = '<div class="content">' . $str . '</div>';
        }

        return $str;
    }

    protected function showErrorpage(int $status, ?string $message): void
    {
        $this->layoutParams->setFallbackLayoutIfNotInitializedYet();
        $this->layoutParams->robotsNoindex = true;
        Yii::$app->response->statusCode    = $status;
        Yii::$app->response->content       = $this->render(
            '@app/views/errors/error',
            [
                'httpStatus' => $status,
                'message'    => $message,
                'name'       => 'Error',
            ]
        );
        Yii::$app->end();
    }

    protected function consultationNotFound(): void
    {
        $url     = Html::encode($this->getParams()->domainPlain);
        $message = str_replace('%URL%', $url, Yii::t('base', 'err_cons_404'));
        $this->showErrorpage(404, $message);
    }

    protected function consultationError(): void
    {
        $this->showErrorpage(500, Yii::t('base', 'err_site_404'));
    }

    protected function checkConsistency(?Motion $checkMotion = null, ?Amendment $checkAmendment = null, bool $throwExceptions = false): void
    {
        $consultationPath = strtolower($this->consultation->urlPath);
        $subdomain        = strtolower($this->site->subdomain);

        if (strtolower($this->consultation->site->subdomain) !== $subdomain) {
            if ($throwExceptions) {
                throw new Internal(Yii::t('base', 'err_cons_not_site'), 400);
            }
            $this->getHttpSession()->setFlash("error", Yii::t('base', 'err_cons_not_site'));
            $this->redirect(UrlHelper::createUrl('/consultation/index'));
        }

        if (is_object($checkMotion) && strtolower($checkMotion->getMyConsultation()->urlPath) !== $consultationPath) {
            if ($throwExceptions) {
                throw new Internal(Yii::t('motion', 'err_not_found'), 404);
            }
            $this->getHttpSession()->setFlash('error', Yii::t('motion', 'err_not_found'));
            $this->redirect(UrlHelper::createUrl('/consultation/index'));
        }

        if ($checkAmendment !== null && ($checkMotion === null || $checkAmendment->motionId !== $checkMotion->id)) {
            if ($throwExceptions) {
                throw new Internal(Yii::t('base', 'err_amend_not_consult'), 400);
            }
            $this->getHttpSession()->setFlash('error', Yii::t('base', 'err_amend_not_consult'));
            $this->redirect(UrlHelper::createUrl('/consultation/index'));
        }
    }

    /**
     * @throws Internal
     * @throws \yii\base\ExitException
     */
    public function loadConsultation(string $subdomain, string $consultationId = '', ?Motion $checkMotion = null, ?Amendment $checkAmendment = null): ?Consultation
    {
        if (is_null($this->site)) {
            $this->site = Site::findOne(['subdomain' => $subdomain]);
        }
        if (is_null($this->site) || $this->site->status == Site::STATUS_DELETED || $this->site->dateDeletion !== null) {
            $this->consultationNotFound();
        }

        if ($consultationId == '') {
            $consultationId = $this->site->currentConsultation->urlPath;
        }

        if (is_null($this->consultation)) {
            $this->consultation = Consultation::findOne(['urlPath' => $consultationId, 'siteId' => $this->site->id]);
            if ($this->consultation && $this->consultation->getSettings()->getSpecializedLayoutClass()) {
                /** @var Layout $layoutClass */
                $layoutClass        = $this->consultation->getSettings()->getSpecializedLayoutClass();
                $this->layoutParams = new $layoutClass();
            }
        }
        if (is_null($this->consultation) || $this->consultation->dateDeletion !== null) {
            $this->consultationNotFound();
        } else {
            Consultation::setCurrent($this->consultation);
        }

        UrlHelper::setCurrentConsultation($this->consultation);
        UrlHelper::setCurrentSite($this->site);

        $this->layoutParams->setConsultation($this->consultation);

        $this->checkConsistency($checkMotion, $checkAmendment);

        return $this->consultation;
    }

    protected function guessRedirectByPrefix(string $prefix): ?string
    {
        $motion = Motion::findOne([
            'consultationId' => $this->consultation->id,
            'titlePrefix'    => $prefix
        ]);
        if ($motion && $motion->isReadable()) {
            return $motion->getLink();
        }

        /** @var Amendment|null $amendment */
        $amendment = Amendment::find()->joinWith('motionJoin')->where([
            'motion.consultationId' => $this->consultation->id,
            'amendment.titlePrefix' => $prefix,
        ])->one();

        if ($amendment && $amendment->isReadable()) {
            return $amendment->getLink();
        }

        return null;
    }

    /**
     * @param string $motionSlug
     * @param bool $throwExceptions
     *
     * @return Motion|null
     */
    protected function getMotionWithCheck($motionSlug, bool $throwExceptions = false)
    {
        if (is_numeric($motionSlug) && $motionSlug > 0) {
            $motion = Motion::findOne([
                'consultationId' => $this->consultation->id,
                'id'             => $motionSlug,
                'slug'           => null
            ]);
        } else {
            $motion = Motion::findOne([
                'consultationId' => $this->consultation->id,
                'slug'           => $motionSlug
            ]);
        }
        /** @var Motion|null $motion */
        if (!$motion) {
            if ($throwExceptions) {
                throw new NotFound('Motion not found', 404);
            }
            $redirect = $this->guessRedirectByPrefix($motionSlug);
            if ($redirect) {
                $this->redirect($redirect);
            } else {
                $this->getHttpSession()->setFlash('error', Yii::t('motion', 'err_not_found'));
                $this->redirect(UrlHelper::createUrl('/consultation/index'));
            }
            Yii::$app->end();

            return null;
        }

        $this->checkConsistency($motion, null, $throwExceptions);

        return $motion;
    }

    /**
     * @param string $motionSlug
     * @param int $amendmentId
     * @param null|string $redirectView
     * @param bool $throwExceptions
     * @return Amendment|null
     */
    protected function getAmendmentWithCheck($motionSlug, $amendmentId, $redirectView = null, bool $throwExceptions = false): ?Amendment
    {
        $motion    = $this->consultation->getMotion($motionSlug);
        $amendment = $this->consultation->getAmendment($amendmentId);
        if (!$amendment || !$motion) {
            if ($throwExceptions) {
                throw new Internal(Yii::t('amend', 'err_not_found'), 404);
            }
            $this->redirect(UrlHelper::createUrl('/consultation/index'));
            return null;
        }
        if ($amendment->motionId !== $motion->id && $amendment->getMyConsultation()->id === $motion->consultationId) {
            if ($throwExceptions) {
                throw new Internal(Yii::t('base', 'err_amend_not_consult'), 404);
            }
            if ($redirectView) {
                $this->redirect(UrlHelper::createAmendmentUrl($amendment, $redirectView));
                return null;
            }
        }
        $this->checkConsistency($motion, $amendment, $throwExceptions);
        return $amendment;
    }
}
