<?php

namespace IjorTengab\IBank\BNI;

use IjorTengab\IBank\IBank;
use IjorTengab\Mission\AbstractWebCrawler;
use IjorTengab\Mission\Exception\ExecuteException;
use IjorTengab\Mission\Exception\StepException;
use IjorTengab\Mission\Exception\VisitException;
use IjorTengab\ActionWrapper\ModuleInterface;
use IjorTengab\IBank\WebCrawlerModuleTrait;
use IjorTengab\DateTime\Range;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;


/**
 * Class sebagai module BNI, menggunakan abstract Web Crawler dan
 * mengimplementasikan interface ModuleInterface agar dapat digunakan
 * oleh ActionWrapper.
 *
 * @link
 *   http://www.bni.co.id/
 *   https://ibank.bni.co.id
 */
class BNI extends AbstractWebCrawler implements ModuleInterface
{
    use WebCrawlerModuleTrait;

    const BNI_MAIN_URL = 'https://ibank.bni.co.id';
    const BNI_DATE_FORMAT = 'd-M-Y';

    /**
     * Internal property.
     */
    protected $username;
    protected $password;
    protected $account;
    // OPR berarti Account type: Tabungan dan Giro.
    // Saat ini baru mendukung tipe ini saja.
    protected $account_type = 'OPR';
    protected $range;
    protected $sort;

    /**
     * @inherit.
     */
    public function defaultCwd()
    {
        return getcwd() . DIRECTORY_SEPARATOR . '.ibank' . DIRECTORY_SEPARATOR . 'BNI';
    }

    /**
     * @inherit
     */
    public function defaultConfiguration()
    {
        $yaml = new Parser();
        try {
            $value = [];
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'BNI-menu.yml'));
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'BNI-target.yml'));
            $value += $yaml->parse(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'BNI-reference.yml'));
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

    /**
     * Override parent::executeBefore()
     * Verifikasi kebutuhan sebelum melanjutkan execute.
     *
     * Catatan tentang validitas mutasi rekening di BNI.
     *
     *  - Tanggal transaksi paling lama yang bisa diambil adalah 6 bulan dari
     *    hari ini. Jika sekarang tanggal 25 januari 2016, maka bila start
     *    date 25-Jul-2015, error yang muncul adalah: "Tanggal Awal tidak boleh
     *    Melebihi 6 Bulan dari Tanggal Hari Ini" dan akan valid jika start_date
     *    24-Jul-2016.
     *
     *  - End date yang lewat dari hari ini, maka error yang muncul adalah:
     *    "Tanggal Akhir tidak boleh melebihi tanggal hari ini".
     *
     *  - Tiap sekali request, maka interval tidak boleh lebih 31 hari, jika
     *    lebih dari 31 hari, error yang muncul adalah: "Transaksi anda tidak
     *    dapat diproses. Periode tanggal yang anda pilih lebih dari 31 hari.
     *    Silahkan masukkan periode tanggal sesuai ketentuan.".
     *
     *  - Untuk support interval lebih dari 31 hari, maka module BNI melakukan
     *    split interval, kemudian melakukan request secara looping.
     *
     *  - Tanggal yang tidak valid (contoh 32-Jul-2015) atau format yang tidak
     *    valid (contoh 1-Aug-2015) maka error yang muncul adalah "Tanggal Akhir
     *    harus menggunakan format yang telah ditentukan dan tanggal yang
     *    valid".
     */
    protected function executeBefore()
    {
        parent::executeBefore();
        if (null === $this->username) {
            $this->log->error('Username belum didefinisikan.');
            throw new ExecuteException;
        }
        if (null === $this->password) {
            $this->log->error('Password belum didefinisikan.');
            throw new ExecuteException;
        }
        switch ($this->target) {
            case 'get_range_transaction':
                if (null === $this->range) {
                    $this->log->error('Range belum didefinisikan.');
                    throw new ExecuteException;
                }
                switch ($this->range) {
                    case 'now':
                    case 'today':
                    case 'last week':
                    case 'last month':
                        break;

                    default:
                        // Verifikasi rangenya.
                        $this->range = Range::create($this->range);
                        if (!$this->range->is_start_valid) {
                            $this->log->notice('Tanggal awal tidak valid. Tanggal otomatis diganti menjadi waktu saat ini.');
                        }
                        if (!$this->range->is_end_valid) {
                            $this->log->notice('Tanggal awal tidak valid. Tanggal otomatis diganti menjadi waktu saat ini.');
                        }
                        // Start date tidak boleh lebih dari 6 bulan sejak hari
                        // ini.
                        $oldest = new \DateTime('6 month ago');
                        // Masih kudu dikurangi satu hari lagi agar tidak
                        // error (lihat catatan pada doc comment fungsi ini).
                        $oldest->sub(new \DateInterval('P1D'));
                        if (!$this->range->isSameDay($oldest, 'start') && !$this->range->comparison($oldest, 'less', 'start')) {
                            $this->log->error('Tanggal Awal tidak boleh kurang dari 6 bulan lalu: {date}', ['date' => $oldest->format('Y-m-d')]);
                            throw new ExecuteException;
                        }

                        // End date tidak boleh lewat dari hari ini.
                        $now = new \DateTime();
                        if (!$this->range->isSameDay($now, 'end') && !$this->range->comparison($now, 'greater', 'end')) {
                            $this->log->error('Tanggal Akhir tidak boleh melebihi Tanggal Hari Ini.');
                            throw new ExecuteException;
                        }
                }

            case 'get_last_transaction':
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


        switch ($this->target) {

        }
    }

    protected function executeAfter()
    {
        parent::executeAfter();
        // Memastikan bahwa url home sudah ada pada configuration.
        $url = $this->configuration('menu][bni_home_page][url');
        if (null === $url && null !== $this->html) {
            $form = $this->html->find('form');
            $url = $form->attr('action');
            $fields = $form->preparePostForm('__HOME__');
            $this->configuration('menu][bni_home_page][url', $url);
            $this->configuration('menu][bni_home_page][fields', $fields);
        }
    }

    /**
     * Override method.
     *
     * Set browser as mobile, and not use curl as library request.
     */
    protected function browserInit()
    {
        parent::browserInit();
        $user_agent_mobile = $this->configuration('user_agent_mobile');
        if (!$user_agent_mobile) {
            $user_agent = $this->browser->getUserAgent('Mobile Browser');
            $this->configuration('user_agent', $user_agent);
            $this->configuration('user_agent_mobile', true);
            $this->browser->options('user_agent', $user_agent);
        }
        $this->browser->curl(false);
    }

    /**
     *
     */
    protected function visitAfter()
    {
        $this->configuration('bni_last_visit', date('c'));
        // Untuk semua visit.
        // Hapus url, agar tidak tersimpan di configuration.
        // Karena url bersifat dinamis.
        $menu_name = $this->step['menu'];
        $this->configuration("menu][$menu_name][url", null);
        // Baru jalankan parent.
        parent::visitAfter();
    }

    /**
     * Karena visitAfter menghapus url, maka kembalikan default.
     */
    protected function resetExecuteAfter()
    {
        $this->configuration('menu][bni_home_page][url', self::BNI_MAIN_URL);
    }

    /**
     * Memastikan bahwa halaman mengandung indikasi yang dibutuhkan untuk
     * nantinya bisa diparsing sesuai dengan target.
     */
    protected function checkIndication($indication_name)
    {
        switch ($indication_name) {
            case '404_page':
                $text = $this->html->find('span#Step1')->text();
                $position = strpos($text, '404');
                return is_int($position);

            case 'home_page_authenticated':
                return ($this->html->find('span#CurrentProfileDisp')->length > 0);

            case 'home_page_anonymous':
                return ($this->html->find('table#Language_table')->length > 0);

            case 'form_exists':
                return ($this->html->find('form')->length > 0);

            case 'login_error':
            case 'select_range_error':
                return ($this->html->find('#Display_MConError')->length > 0);

            case 'table_balance':
                return ($this->html->find('table[id~=BalanceDisplayTable]')->eq(1)->length > 0);

            case 'table_account':
                return ($this->html->find('table#AccountMenuList_table')->length > 0);

            case 'mini_statement_select_account_number':
                return ($this->html->find('select[name=MiniStmt]')->length > 0);

            case 'mini_statement_page':
                return ($this->html->find('input[name=PageName][value=OperMiniAccIDSelectRq]')->length > 0);

            case 'select_range_page':
                return ($this->html->find('#Search_Criteria_tr')->length > 0);

            case 'table_transaction_page':
                return ($this->html->find('input[name=page][value=FullStmtInqRq]')->length > 0);

            case 'session_destroy':
                return ($this->html->find('input[name=page][value=SessionErrorMessage]')->length > 0);
        }
    }

    /**
     * Parsing menu "home_page" context "authenticated" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_home_page_authenticated.
     */
    protected function bniParseHomePageAuthenticated()
    {
        switch ($this->target) {
            case 'get_balance':
            case 'get_last_transaction':
            case 'get_range_transaction':
                $url_account_page = $this->html->find('td a')->eq(0)->attr('href');
                if (empty($url_account_page)) {
                    $this->log->error('Url for menu "account_page" not found.');
                    throw new VisitException;
                }
                $this->configuration('menu][bni_account_page][url', $url_account_page);
                break;
        }
    }

    /**
     * Parsing menu "home_page" context "anonymous" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_home_page_anonymous.
     */
    protected function bniParseHomePageAnonymous()
    {
        switch ($this->target) {
            // Apapun targetnya, aktivitasnya sama.
            default:
                // Belum login, maka tambah langkah baru.
                $this->addStepFromReference('home_page');

                // Cari tahu bahasa situs, penting untuk parsing yang
                // terkait bahasa.
                if ($this->html->find('span.Languageleftselect')->length) {
                    $this->configuration('language', 'id');
                }
                elseif ($this->html->find('span.Languagerightselect')->length) {
                    $this->configuration('language', 'en');
                }

                // Cari url untuk ke halaman login_page.
                $url_login_page = $this->html->find('#RetailUser')->attr('href');
                if (empty($url_login_page)) {
                    $this->log->error('Url for menu login_page not found.');
                    throw new VisitException;
                }
                $this->configuration('menu][bni_login_page][url', $url_login_page);
                break;
        }
    }

    /**
     * Parsing menu "login_page" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_login_page.
     */
    protected function bniParseLoginPage()
    {
        switch ($this->target) {
            // Apapun targetnya, aktivitasnya sama.
            default:
                $url = $this->html->find('form')->attr('action');
                if (empty($url)) {
                    $this->log->error('Url for form "login_form" not found.');
                    throw new VisitException;
                }
                if (empty($this->username) || empty($this->password)) {
                    $this->log->error('Username and Password required.');
                    throw new VisitException;
                }
                $fields = $this->html->find('form')->extractForm();
                $fields['__AUTHENTICATE__'] = 'Login';
                $fields['CorpId'] = $this->username;
                $fields['PassWord'] = $this->password;
                $this->configuration('menu][bni_login_form][url', $url);
                $this->configuration('menu][bni_login_form][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "account_page" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_account_page.
     */
    protected function bniParseAccountPage()
    {
        switch ($this->target) {
            case 'get_balance':
                $url_balance_inquiry_page = $this->html->find('td a')->eq(0)->attr('href');
                if (empty($url_balance_inquiry_page)) {
                    $this->log->error('Url for menu "balance_inquiry_page" not found.');
                    throw new VisitException;
                }
                $this->configuration('menu][bni_balance_inquiry_page][url', $url_balance_inquiry_page);
                break;

            case 'get_last_transaction':
                $url_mini_statement_page = $this->html->find('td a')->eq(1)->attr('href');
                if (empty($url_mini_statement_page)) {
                    $this->log->error('Url for menu "mini_statement_page" not found.');
                    throw new VisitException;
                }
                $this->configuration('menu][bni_mini_statement_page][url', $url_mini_statement_page);
                break;

            case 'get_range_transaction':
                $url_transaction_history_page = $this->html->find('td a')->eq(2)->attr('href');
                if (empty($url_transaction_history_page)) {
                    $this->log->error('Url for menu "transaction_history_page" not found.');
                    throw new VisitException;
                }
                $this->configuration('menu][bni_transaction_history_page][url', $url_transaction_history_page);
                break;
        }
    }

    /**
     * Parsing menu "account_type_form" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_account_type_form.
     */
    protected function bniParseAccountTypeForm()
    {
        switch ($this->target) {
            case 'get_balance':
            case 'get_last_transaction':
            case 'get_range_transaction':
                $form = $this->html->find('form');
                $url = $form->attr('action');
                if (empty($url)) {
                    $this->log->error('Url for form "account_type_form" not found.');
                    throw new VisitException;
                }
                $fields = $form->preparePostForm('AccountIDSelectRq');
                //
                $fields['MAIN_ACCOUNT_TYPE'] = $this->account_type;
                $this->configuration('menu][bni_account_type_form][url', $url);
                $this->configuration('menu][bni_account_type_form][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "account_number_form" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_account_number_form.
     */
    protected function bniParseAccountNumberForm()
    {
        switch ($this->target) {
            case 'get_balance':
                $form = $this->html->find('form');
                $url = $form->attr('action');
                if (empty($url)) {
                    $this->log->error('Url for form "account_number_form" not found.');
                    throw new VisitException;
                }
                $fields = $form->preparePostForm('BalInqRq');
                // Todo, support multi account number.
                // $fields['acc1'] = '';
                $this->configuration('menu][bni_account_number_form][url', $url);
                $this->configuration('menu][bni_account_number_form][fields', $fields);
                break;

            case 'get_last_transaction':
                $form = $this->html->find('form');
                $url = $form->attr('action');
                if (empty($url)) {
                    $this->log->error('Url for form "account_number_form" not found.');
                    throw new VisitException;
                }
                $fields = $form->preparePostForm('Go');
                // Cari nomor rekening.
                $value = null;
                foreach ($fields['MiniStmt'] as $number) {
                    if (strpos($number, $this->account) !== false) {
                        $value = $number;
                    }
                }
                $fields['MiniStmt'] = $value;
                $this->configuration('menu][bni_account_number_form][url', $url);
                $this->configuration('menu][bni_account_number_form][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "balance_inquiry_page" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_balance_inquiry_page.
     */
    protected function bniParseBalanceInquiryPage()
    {
        switch ($this->target) {
            case 'get_balance':
                // Get Balance.
                $indication_table_balance = $this->html->find('table[id~=BalanceDisplayTable]')->eq(1);
                $span = $indication_table_balance->find('tr#Row5_5 td#Row5_5_column2 span');
                $balance = $span->text();
                $this->result = $balance;

                // Keep information of home_page
                $this->bniSaveUrlHomePage();
                break;
        }
    }

    // Keep information of home_page
    protected function bniSaveUrlHomePage()
    {
        $form = $this->html->find('form');
        $url = $form->attr('action');
        $fields = $form->preparePostForm('__HOME__');
        $this->configuration('menu][bni_home_page][url', $url);
        $this->configuration('menu][bni_home_page][fields', $fields);
    }

    /**
     * Parsing menu "404_page" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_404_page.
     */
    protected function bniParse404Page()
    {
        switch ($this->target) {
            default:
                // 404 terjadi, maka tambah langkah baru.
                // Temukan url login.
                $url = $this->html->find('a#Login')->attr('href');
                if (empty($url)) {
                    $this->log->error('Url for menu "login_page" not found.');
                    throw new VisitException;
                }
                $this->configuration('menu][bni_login_page][url', $url);
                $this->addStepFromReference('404_page');
                break;
        }
    }

    /**
     * Alternative handler for bni_parse_login_form_error.
     */
    protected function bniParseLoginFormError()
    {
        $text = $this->html->find('#Display_MConError')->text();
        $text = preg_replace('/\s\s+/', ' ', $text);
        $text = trim($text);
        $this->log->error('Login failed. Message: {text}', ['text' => $text]);
        throw new VisitException;
    }

    /**
     * Alternative handler for bni_parse_select_range_error.
     */
    protected function bniParseSelectRangeError()
    {
        $text = $this->html->find('#Display_MConError')->text();
        $text = preg_replace('/\s\s+/', ' ', $text);
        $text = trim($text);
        $this->log->error('Select Range failed. Message: {text}', ['text' => $text]);
        throw new VisitException;
    }

    /**
     * Alternative handler for bni_parse_mini_statement_page.
     */
    protected function bniParseMiniStatementPage()
    {
        switch ($this->target) {
            case 'get_last_transaction':
                $language = $this->configuration('language');
                $tables = $this->html->find('div#TitleBar > table')->extractTable(true);
                if (empty($tables)) {
                    $this->log->error('Table for Statement not found.');
                    throw new VisitException;
                }
                $transactions = [];
                while ($table = array_shift($tables)) {
                    if (isset($table[0]) && isset($table[1])) {
                        $info = $language == 'id' ? $this->bniTranslate($table[0]) : $table[0];
                        $value = $table[1];
                        switch ($info) {
                            case 'Transaction Date':
                                $transaction = [];
                                $transaction['date'] = $value;
                                break;

                            case 'Transaction Remarks':
                                $transaction['detail'] = $value;
                                break;

                            case 'Amount type':
                                $transaction['type'] = $value;
                                break;

                            case 'Amount':
                                $transaction['amount'] = $value;
                                break;

                            case 'Account Balance':
                                $transaction['balance'] = $value;
                                $transactions[] = $transaction;
                                break;
                        }
                    }
                }
                // Sort.
                if ($this->sort == 'asc') {
                    krsort($transactions);
                    $transactions = array_values($transactions);
                }
                // Set to result.
                $this->result = $transactions;

                // Keep information of home_page.
                $this->bniSaveUrlHomePage();
                // Tapi ada yang perlu diedit, karena isian pilih rekening
                // ternyata element select sehingga perlu kita ganti.
                $fields = $this->configuration('menu][bni_home_page][fields');
                $value = null;
                foreach ($fields['MiniStmt'] as $number) {
                    if (strpos($number, $this->account) !== false) {
                        $value = $number;
                    }
                }
                $fields['MiniStmt'] = $value;
                $this->configuration('menu][bni_home_page][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "select_range_page" sesuai dengan kebutuhan
     * pada property $target.
     * Alternative handler for bni_parse_select_range_page.
     */
    protected function bniParseSelectRangePage()
    {
        switch ($this->target) {
            case 'get_range_transaction':
                $form = $this->html->find('form');
                $url = $form->attr('action');
                $fields = $form->preparePostForm('FullStmtInqRq');
                // Untuk kasus range yang spesifik dan sering digunakan, maka
                // tidak perlu diconvert ke object Range. Langsung aja,
                // gak pake lama.
                $fields['Search_Option'] = 'TxnPrd';
                switch ($this->range) {
                    case 'now':
                    case 'today':
                        $fields['TxnPeriod'] = 'Today';
                        break;

                    case 'last week':
                        $fields['TxnPeriod'] = 'LastWeek';
                        break;

                    case 'last month':
                        $fields['TxnPeriod'] = 'LastMonth';
                        break;

                    default:
                        $fields['TxnPeriod'] = '-1';
                        $fields['Search_Option'] = 'Date';

                        // Aturan BNI adalah sekali request, maka total maksimal
                        // 31 hari, berarti antara start dan end ada 30 hari.
                        if ($this->range->isSameMonth() || $this->range->diff()->days <= 30) {
                            // tidak perlu dipecah.
                            $fields['txnSrcFromDate'] = $this->range->format(self::BNI_DATE_FORMAT, 'start');
                            $fields['txnSrcToDate'] = $this->range->format(self::BNI_DATE_FORMAT, 'end');
                        }
                        else {
                            // Untuk interval lebih dari 30 hari, maka kita perlu
                            // pecah menjadi per bulan.
                            $this->configuration('temporary][over_range', true);
                            $this->range = $this->range->splitPerMonth();
                            // Hasil split per month adalah asc, maka sesuaikan
                            if ($this->sort == 'desc') {
                                krsort($this->range);
                            }

                            $first = array_shift($this->range);
                            $fields['txnSrcFromDate'] = $first->format(self::BNI_DATE_FORMAT, 'start');
                            $fields['txnSrcToDate'] = $first->format(self::BNI_DATE_FORMAT, 'end');
                        }
                        break;
                }
                $this->configuration('menu][bni_select_range_form][url', $url);
                $this->configuration('menu][bni_select_range_form][fields', $fields);
                break;
        }
    }

    /**
     * Alternative handler for bni_parse_transaction_page.
     */
    protected function bniParseTransactionPage()
    {
        switch ($this->target) {
            case 'get_range_transaction':
                $tables = $this->html->extractTable(true);
                $transaction = $this->bniFilterTransactionTable($tables);
                $temporary_result = $this->configuration('temporary][result');
                if (null === $temporary_result) {
                    $temporary_result = [];
                }
                $temporary_result = array_merge($temporary_result, $transaction);
                $this->configuration('temporary][result', $temporary_result);
                break;
        }
    }

    /**
     * Untuk range yang lebih dari 31 hari, maka perlu dilakukan split.
     * Alternative handler for
     * bni_parse_if_over_range_then_save_select_range_page_location.
     */
    protected function bniParseIfOverRangeThenSaveSelectRangePageLocation()
    {
        $is_over_range = $this->configuration('temporary][over_range');
        if ($is_over_range) {
            // Cek lagi apakah sudah selesai loopingnya.
            // looping akan dikurangi oleh handler
            // bni_parse_select_range_page_revisited
            if (empty($this->range)) {
                // Hapus informasi over_range.
                $this->configuration('temporary][over_range', false);
                return;
            }

            $form = $this->html->find('form');
            $url = $form->attr('action');

            $fields = $form->preparePostForm('__BACK__');
            $this->configuration('menu][bni_select_range_page][url', $url);
            $this->configuration('menu][bni_select_range_page][fields', $fields);
        }
    }

    /**
     * Alternative handler for
     * bni_parse_if_has_next_page_then_visit_transaction_next_page_prepend.
     */
    protected function bniParseIfHasNextPageThenVisitTransactionNextPagePrepend()
    {
        // cari link berikutnya
        try {
            $url_raw = $this->html->find('a#NextData')->attr('href');
            if (null === $url_raw) {
                throw new \Exception;
            }
            preg_match('/^javascript\:fnCallAJAX\(\'(.*)\'\)$/', $url_raw, $m);
            if (empty($m) || !array_key_exists(1, $m)) {
                throw new \Exception;
            }
            $url = $m[1];
            $this->addStepFromReference('transaction_next_page');
            $this->configuration('menu][bni_transaction_next_page][url', $url);
        }
        catch (\Exception $e) {
            // Stop.
        }
    }

    /**
     * Alternative handler for
     * bni_parse_if_over_range_then_visit_select_range_page_append.
     */
    protected function bniParseIfOverRangeThenVisitSelectRangePageAppend()
    {
        $is_over_range = $this->configuration('temporary][over_range');
        if ($is_over_range) {
            $this->addStepFromReference('revisit_select_range_page');
        }
    }

    /**
     * Alternative handler for: bni_parse_select_range_page_revisited
     */
    protected function bniParseSelectRangePageRevisited()
    {
        $next = array_shift($this->range);
        $form = $this->html->find('form');
        $url = $form->attr('action');
        $fields = $form->preparePostForm('FullStmtInqRq');
        $fields['TxnPeriod'] = '-1';
        $fields['Search_Option'] = 'Date';
        $fields['txnSrcFromDate'] = $next->format(self::BNI_DATE_FORMAT, 'start');
        $fields['txnSrcToDate'] = $next->format(self::BNI_DATE_FORMAT, 'end');
        $this->addStepFromReference('revisit_select_range_form');
        $this->configuration('menu][bni_select_range_form][url', $url);
        $this->configuration('menu][bni_select_range_form][fields', $fields);

    }

    /**
     * Mendapatkan array transaksi dengan format yang sudah rapih.
     */
    protected function bniFilterTransactionTable($tables)
    {
        $ref = IBank::reference('table_header_account_statement');
        $ref = array_flip($ref);

        $language = $this->configuration('language');
        $language = null === $language ? 'id' : $language;

        $transactions = [];
        while (!empty($tables)) {
            $table = array_shift($tables);

            if (isset($table[0]) && isset($table[1])) {
                $info = $language == 'id' ? $this->bniTranslate($table[0]) : $table[0];
                $value = $table[1];
                switch ($info) {
                    case 'Transaction Date':
                        $transaction = ['no' => null, 'id' => null];
                        $transaction['date'] = $value;
                        break;

                    case 'Transaction Remarks':
                        $transaction['detail'] = $value;
                        break;

                    case 'Amount type':
                        $transaction['type'] = $value;
                        break;

                    case 'Amount':
                        $transaction['amount'] = $value;
                        break;

                    case 'Account Balance':
                        $transaction['balance'] = $value;
                        $transactions[] = array_merge($ref, $transaction);
                        break;
                }
            }
        }
        return $transactions;
    }

    /**
     * Alternative handler for bni_transaction_finishing.
     */
    protected function bniTransactionFinishing()
    {
        $temporary_result = $this->configuration('temporary][result');
        $this->configuration('temporary][result', null);

        // Secara default, bni menggunakan sort secara desc.
        switch ($this->sort) {
            case 'desc':
                break;

            case 'asc':
                krsort($temporary_result);
                break;
        }
        if (null === $this->result) {
            $this->result = [];
        }
        $this->result = array_merge($this->result, $temporary_result);
    }

    /**
     * Translate dari indonesia ke inggiris.
     */
    protected function bniTranslate($string)
    {
        return isset($this->bniString()[$string]) ? $this->bniString()[$string] : $string;
    }

    /**
     * Kamus.
     */
    protected function bniString()
    {
        return [
            'Tanggal Transaksi' => 'Transaction Date',
            'Uraian Transaksi' => 'Transaction Remarks',
            'Tipe' => 'Amount type',
            'Jumlah Pembayaran' => 'Amount',
            'Nominal' => 'Amount',
            'Saldo' => 'Account Balance',
        ];
    }

    protected function bniCheckRange()
    {
        if (null === $this->range) {
            $this->changeTarget('get_last_transaction');
        }
        else {
            $this->changeTarget('get_range_transaction');
        }
    }






}