<?php

namespace app\models\settings;

use app\components\UrlHelper;
use app\models\db\Consultation;

class Layout
{
    public $menu            = [];
    public $breadcrumbs     = null;
    public $multimenu       = [];
    public $preSidebarHtml  = '';
    public $postSidebarHtml = '';
    public $menusHtml       = [];
    public $robotsNoindex   = false;
    public $extraCss        = [];
    public $extraJs         = [];
    public $onloadJs        = [];
    public $fullWidth       = false;
    public $fullScreen      = false;

    /** @var Consultation|null */
    private $consultation;


    /**
     * @param Consultation $consultation
     */
    public function setConsultation(Consultation $consultation)
    {
        $this->consultation = $consultation;
        if ($consultation && count($this->breadcrumbs) == 0) {
            $this->breadcrumbs[UrlHelper::createUrl('consultation/index')] = $consultation->titleShort;
        }
    }

    /**
     * @param string $file
     * @return $this;
     */
    public function addCSS($file)
    {
        if (!in_array($file, $this->extraCss)) {
            $this->extraCss[] = $file;
        }
        return $this;
    }

    /**
     * @param string $exec_js
     * @return $this;
     */
    public function addOnLoadJS($exec_js)
    {
        $this->onloadJs[] = $exec_js;
        return $this;
    }

    /**
     * @param string $file
     * @return $this;
     */
    public function addJS($file)
    {
        if (!in_array($file, $this->extraJs)) {
            $this->extraJs[] = $file;
        }
        return $this;
    }

    /**
     * @param string $name
     * @param null|string $path
     * @return $this
     */
    public function addBreadcrumb($name, $path = null)
    {
        if ($path) {
            $this->breadcrumbs[$path] = $name;
        } else {
            $this->breadcrumbs[] = $name;
        }
        return $this;
    }
}
