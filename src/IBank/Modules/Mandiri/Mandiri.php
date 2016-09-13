<?php

namespace IjorTengab\IBank\Modules\Mandiri;

use IjorTengab\IBank\IBank;
use IjorTengab\Mission\AbstractWebCrawler;
use IjorTengab\ActionWrapper\ModuleInterface;
use IjorTengab\IBank\WebCrawlerModuleTrait;
use IjorTengab\DateTime\Range;
use IjorTengab\Logger\Log;
use IjorTengab\ParseHTMLAdvanced;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use IjorTengab\Mission\Exception\ExecuteException;
use IjorTengab\Mission\Exception\StepException;
use IjorTengab\Mission\Exception\VisitException;

/**
 * Class sebagai module Mandiri, menggunakan abstract Web Crawler dan
 * mengimplementasikan interface ModuleInterface agar dapat digunakan
 * oleh ActionWrapper.
 *
 * @link
 *   http://www.bankmandiri.co.id/
 *   https://ib.bankmandiri.co.id/
 */
class Mandiri extends AbstractWebCrawler implements ModuleInterface
{
    use WebCrawlerModuleTrait;

    const MANDIRI_MAIN_URL = 'https://ib.bankmandiri.co.id/';
    const MANDIRI_DATE_FORMAT = 'dmY';
    // const MANDIRI_DATE_FORMAT_DAILY_DATE = 'd';
    // const MANDIRI_DATE_FORMAT_DAILY_MONTH = 'n';
    // const MANDIRI_DATE_FORMAT_DAILY_YEAR = 'Y';

    /**
     * Internal property.
     */
    protected $username;
    protected $password;
    protected $account;
    protected $range;
    protected $sort;

    /**
     * @inherit.
     */
    public function defaultCwd()
    {
        return getcwd() . DIRECTORY_SEPARATOR . '.ibank' . DIRECTORY_SEPARATOR . 'Mandiri';
    }

    /**
     * @inherit
     */
    public function defaultConfiguration()
    {
        $yaml = new Parser();
        try {
            $value = [];
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Mandiri-menu.yml'));
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Mandiri-target.yml'));
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Mandiri-reference.yml'));
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
        parent::executeBefore();
        
        // $c = file_get_contents('C:\Users\X220\.ibank\debug\response_body.html');
        
        
        // $form = ParseHTMLAdvanced::init($c, 'form[name=LoginForm][action=/retail/Login.do]');
        
        // $this->html = new ParseHTMLAdvanced($c);
        
        
        // $this->configuration('temporary][form', $form);
        
        // $this->mandiriParseHomePageAnonymous();
        // throw new ExecuteException;
        
        
        
        
        
        return;
        /* 
        switch ($this->target) {
            case 'logout':
                break;

            default:
                if (null === $this->username) {
                    $this->log->error('Username belum didefinisikan.');
                    throw new ExecuteException;
                }
                if (null === $this->password) {
                    $this->log->error('Password belum didefinisikan.');
                    throw new ExecuteException;
                }
                break;
        }
        switch ($this->target) {
            case 'get_transaction':
                if (null === $this->range) {
                    // Mandiri tidak ada mini account statement seperti BNI, maka
                    // jika null kita anggap saja today.
                    $this->range = 'today';
                    $this->log->notice('Range belum didefinisikan, otomatis mencari transaksi hari ini.');
                }
                $this->range = Range::create($this->range);
                // Mandiri paling lama adalah awal bulan dari 2 bulan lalu.
                $oldest = new \DateTime('first day of 2 months ago');
                if (!$this->range->comparison($oldest, 'less', 'start')) {
                    // Tapi kalo masih di hari yang sama, ya gpp.
                    if (!$this->range->isSameDay($oldest, 'start')) {
                        $this->log->error('Tanggal Awal tidak boleh kurang dari hari pertama dari 2 bulan lalu: {date}', ['date' => $oldest->format('Y-m-d')]);
                        throw new ExecuteException;
                    }
                }

                // End date tidak boleh lewat dari hari ini.
                $now = new \DateTime();
                if (!$this->range->comparison($now, 'greater', 'end')) {
                    // Tapi kalo masih di hari yang sama, ya gpp.
                    if (!$this->range->isSameDay($now, 'end')) {
                        $this->log->error('Tanggal Akhir tidak boleh melebihi Tanggal Hari Ini.');
                        throw new ExecuteException;
                    }
                }
                switch ($this->sort) {
                    case 'asc':
                    case 'desc':
                        break;

                    case 'ASC':
                    case 'ascending':
                    case 'ASCENDING':
                        $this->sort = 'asc';
                        break;

                    case 'descending':
                    case 'DESC':
                    case 'DESCENDING':
                        $this->sort = 'desc';
                        break;

                    default:
                        $this->sort = 'desc';
                        $this->log->notice('Transaksi otomatis diurut dengan pola descending.');
                        break;
                }

                break;

            default:
                // Do something.
                break;
        }
 */

    }

    /**
     * Override method.
     *
     * Set browser as mobile, and not use curl as library request.
     */
    protected function browserInit()
    {
        parent::browserInit();
        $this->browser->curl(false);
    }

    protected function checkIndication($indication_name)
    {
        switch ($indication_name) {
            case 'home_page_anonymous':
                $form = $this->html->find('form[name=LoginForm][action=/retail/Login.do]');
                $this->configuration('temporary][form', $form);
                return ($form->length > 0);

            // case 'home_page_authenticated':
                // return ($this->html->find('frameset')->length > 0);

            // case 'table_exists':
                // $table = $this->html->find('table');
                // $this->configuration('temporary][table', $table);
                // return ($table->length > 0);

            // case 'select_range_form':
                // $form = $this->html->find('form[name=iBankForm][action=/accountstmt.do]');
                // $this->configuration('temporary][form', $form);
                // return ($form->length > 0);

            // case 'redirect_to_main':
                // $text = $this->html->text();
                // return (strpos($text, "window.parent.location.href = 'main.jsp'") === 0);

            // case 'redirect_to_main':
                // $text = $this->html->text();
                // return (strpos($text, "window.parent.location.href = 'main.jsp'") === 0);

            // case 'table_transaction_page':
                // $table = $this->html->find('body > table')->eq(2)->find('table')->eq(1);
                // $this->configuration('temporary][table', $table);
                // return ($table->length > 0);
        }
    }

    protected function visitBefore()
    {
        parent::visitBefore();
    }

    protected function visitAfter()
    {
        $this->configuration('bca_last_visit', date('c'));
        parent::visitAfter();
    }

    protected function mandiriParseHomePageAnonymous() 
    {
        switch ($this->target) {
            default:
                $form = $this->configuration('temporary][form');
                $fields = $form->preparePostForm('image');
                $fields['userID'] = $this->username;
                $fields['password'] = $this->password;
                unset($fields['image']);
                $fields += $this->mandiriPopulateImageFields();
                $this->configuration('menu][mandiri_login_form][fields', $fields);
                
                // $debugname = 'fields'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                
                // Cari tahu isian field image.x dan image.y
                // Kemungkinan ini adalah manipulasi javascript.
                // $image = $this->html->find();
                
                
                // unset($fields['txtUserId']);
                // $fields['value(user_id)'] = $this->username;
                // $fields['value(pswd)'] = $this->password;
                // $this->addStepFromReference('login_form');
                // $this->configuration('menu][bca_login_form][fields', $fields);
                // break;
        }
    }
    
    protected function mandiriPopulateImageFields() 
    {
        // Untuk saat ini fix dulu ajah.
        return [
            'image.x' => '0',
            'image.y' => '0',
        ];
        
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
