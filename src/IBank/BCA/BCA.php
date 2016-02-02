<?php

namespace IjorTengab\IBank\BCA;

use IjorTengab\Mission\AbstractWebCrawler;
use IjorTengab\ActionWrapper\ModuleInterface;
use IjorTengab\IBank\WebCrawlerModuleTrait;
use IjorTengab\DateTime\Range;
use IjorTengab\Logger\Log;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use IjorTengab\Mission\Exception\ExecuteException;
use IjorTengab\Mission\Exception\StepException;
use IjorTengab\Mission\Exception\VisitException;


/**
 * Class sebagai module BCA, menggunakan abstract Web Crawler dan
 * mengimplementasikan interface ModuleInterface agar dapat digunakan
 * oleh ActionWrapper.
 *
 * @link
 *   http://www.bni.co.id/
 *   https://ibank.bni.co.id
 */
class BCA extends AbstractWebCrawler implements ModuleInterface
{
    use WebCrawlerModuleTrait;

    const BCA_MAIN_URL = 'https://ibank.klikbca.com';
    const BCA_DATE_FORMAT = 'd-M-Y';

    /**
     * Internal property.
     */
    protected $username;
    protected $password;
    protected $account;
    // OPR berarti Account type: Tabungan dan Giro.
    // Saat ini baru mendukung tipe ini saja.
    // protected $account_type = 'OPR';
    protected $range;
    protected $sort;

    /**
     * @inherit.
     */
    public function defaultCwd()
    {
        return getcwd() . DIRECTORY_SEPARATOR . '.ibank' . DIRECTORY_SEPARATOR . 'BCA';
    }

    /**
     * @inherit
     */
    public function defaultConfiguration()
    {
        $yaml = new Parser();
        try {
            $value = [];
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'BCA-menu.yml'));
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'BCA-target.yml'));
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'BCA-reference.yml'));
            return $value;
        } catch (ParseException $e) {
            $this->log->error('Unable to parse the YAML string: {string}', ['string' => $e->getMessage()]);
        }
    }

    protected function init()
    {
        // Default nama file untuk keperluan debug.
        $_ = DIRECTORY_SEPARATOR;
        $this->configuration('temporary][browser][browser_history', '..' . $_ . 'debug' . $_ . 'history.log');
        $this->configuration('temporary][browser][browser_response_body', '..' . $_ . 'debug' . $_ . 'response_body.html');
    }

    /**
     * Override method.
     *
     * Set property information in object.
     *
     * @param $property string
     *   Parameter dapat bernilai sebagai berikut:
     *   - username
     *     Username for login.
     *   - password
     *     Password for login.
     *   - account
     *     Account Number.
     *   dan property lainnya yang dijelaskan pada parent::set().
     */
    public function set($property, $value)
    {
        switch ($property) {
            case 'username':
            case 'password':
            case 'account':
            case 'range':
            case 'sort':
                $this->{$property} = $value;
                break;
        }
        return parent::set($property, $value);
    }

    protected function executeBefore()
    {


        // $last_visit = $this->configuration('last_visit');

        // $thisStep = $this->step;
        // $thisSteps = $this->steps;
        // $debugname = 'thisStep'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        // $debugname = 'thisSteps'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        // if () {

        // }

    // - handler: visit
      // menu: home_page
      // visit_after: verify

        // throw new ExecuteException;

    }

    protected function executeAfter()
    {
        // $result = $this->browser->result;
        // $debugname = 'result'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
    }

    /**
     * Override method.
     *
     * Set browser as mobile, and not use curl as library request.
     */
    protected function browserInit()
    {
        parent::browserInit();
    }


    protected function visitBefore()
    {
        parent::visitBefore();
    }

    protected function visitAfter()
    {
        parent::visitAfter();
        $this->configuration('last_visit', date('c'));
    }

    /**
     * Session expired setelah 8 menit, sesuai dengan informasi pada
     * javascript pada BCA.
     */
    protected function bcaCheckSession()
    {
        $skip = false;
        // Menggunakan try catch, karena pembentukan object DateTime kalau gagal
        // akan throw Exception.
        try {
            $last_visit = $this->configuration('last_visit');
            if ($last_visit === null) {
                throw new \Exception;
            }
            $now = new \DateTime;
            $expired = new \DateTime($last_visit);
            $expired->add(new \DateInterval('PT8M'));
            if ($now > $expired) {
                throw new \Exception;
            }
        }
        catch (\Exception $e) {
            $skip = true;
        }
        if ($skip) {
            $this->resetExecute();
            $this->addStepFromReference('home_page');
            throw new StepException;
        }
    }


    protected function bcaSetReferer()
    {
        $menu_name = $this->step['menu'];
        $referer = $this->configuration("menu][$menu_name][referer");
        if (empty($referer)) {
            return;
        }
        $language = $this->configuration('language');
        switch ($language) {
            case 'en':
                $part = 'nav_bar_indo'; // Masih belum tahu.
                break;

            case 'id':
            default:
                $part = 'nav_bar_indo';
                break;
        }
        $referer = Log::interpolate($referer, ['language' => $part]);
        $this->browser->headers('Referer', $referer);
    }

    protected function bcaMethodPost()
    {
        $this->browser->options('method', 'POST');
    }

    protected function checkIndication($indication_name)
    {
        switch ($indication_name) {
            case 'home_page_anonymous':
                $form = $this->html->find('form[name=iBankForm][action=/authentication.do]');
                $this->configuration('temporary][form', $form);
                return ($form->length > 0);

            case 'home_page_authenticated':
                return ($this->html->find('frameset')->length > 0);

            case 'table_exists':
                $table = $this->html->find('table');
                $this->configuration('temporary][table', $table);
                return ($table->length > 0);

            case 'select_range_form':
                $form = $this->html->find('form[name=iBankForm][action=/accountstmt.do]');
                $this->configuration('temporary][form', $form);
                return ($form->length > 0);

            case 'redirect_to_main':
                $text = $this->html->text();
                return (strpos($text, "window.parent.location.href = 'main.jsp'") === 0);
        }
    }

    /**
     * Alternative handler for bca_parse_home_page_anonymous.
     */
    protected function bcaParseHomePageAnonymous()
    {
        switch ($this->target) {
            default:
                $form = $this->configuration('temporary][form');
                $fields = $this->html->preparePostForm('value(Submit)');
                unset($fields['txtUserId']);
                $fields['value(user_id)'] = $this->username;
                $fields['value(pswd)'] = $this->password;
                $this->addStepFromReference('login_form');
                $this->configuration('menu][login_form][fields', $fields);
                break;
        }
    }

    /**
     * Alternative handler for bca_parse_home_page_authenticated.
     */
    protected function bcaParseHomePageAuthenticated()
    {
        switch ($this->target) {
            default:
                // Cari bahasa
                $src = $this->html->find('frameset > frame[name=menu]')->attr('src');
                if (strpos($src, 'nav_bar_indo') === 0) {
                    $this->configuration('language', 'id');
                }
                else {
                    $this->configuration('language', 'en');
                }
                break;
        }
    }

    /**
     * Alternative handler for bca_parse_balance_inquiry_page.
     */
    protected function bca_parse_balance_inquiry_page()
    {
        switch ($this->target) {
            default:
                $table = $this->configuration('temporary][table');
                $info = $table->eq('2')->extractTable(true);
                $balance = isset($info[1][3]) ? $info[1][3] : null;
                $this->result = $balance;
                break;
        }
    }

    protected function bca_parse_select_range_form()
    {
        $form = $this->configuration('temporary][form');

    }

    protected function bca_parse_redirect_to_main()
    {
        switch ($this->target) {
            case 'get_balance':
                $this->resetExecute();
                $this->addStepFromReference('home_page');
                break;
        }
    }









}
