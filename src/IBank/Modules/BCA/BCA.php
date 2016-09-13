<?php

namespace IjorTengab\IBank\Modules\BCA;

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
 * Class sebagai module BCA, menggunakan abstract Web Crawler dan
 * mengimplementasikan interface ModuleInterface agar dapat digunakan
 * oleh ActionWrapper.
 *
 * @link
 *   http://www.bni.co.id/
 *   http://www.klikbca.com/
 *   https://ibank.klikbca.com/
 */
class BCA extends AbstractWebCrawler implements ModuleInterface
{
    use WebCrawlerModuleTrait;

    const BCA_MAIN_URL = 'https://ibank.klikbca.com';
    const BCA_DATE_FORMAT = 'dmY';
    const BCA_DATE_FORMAT_DAILY_DATE = 'd';
    const BCA_DATE_FORMAT_DAILY_MONTH = 'n';
    const BCA_DATE_FORMAT_DAILY_YEAR = 'Y';

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
        parent::executeBefore();
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
                    // BCA tidak ada mini account statement seperti BNI, maka
                    // jika null kita anggap saja today.
                    $this->range = 'today';
                    $this->log->notice('Range belum didefinisikan, otomatis mencari transaksi hari ini.');
                }
                $this->range = Range::create($this->range);
                // BCA paling lama adalah awal bulan dari 2 bulan lalu.
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

            case 'redirect_to_main':
                $text = $this->html->text();
                return (strpos($text, "window.parent.location.href = 'main.jsp'") === 0);

            case 'table_transaction_page':
                $table = $this->html->find('body > table')->eq(2)->find('table')->eq(1);
                $this->configuration('temporary][table', $table);
                return ($table->length > 0);
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

    /**
     * Session expired setelah 8 menit, sesuai dengan informasi pada
     * javascript pada BCA.
     * Alternative handler for bca_check_session.
     */
    protected function bcaCheckSession()
    {
        $skip = false;
        // Menggunakan try catch, karena pembentukan object DateTime kalau gagal
        // akan throw Exception.
        try {
            $last_visit = $this->configuration('bca_last_visit');
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

    /**
     * Alternative handler for bca_set_referer.
     */
    protected function bcaSetReferer()
    {
        $menu_name = $this->step['menu'];
        $referer = $this->configuration("menu][bca_$menu_name][referer");
        if (empty($referer)) {
            return;
        }
        $language = $this->configuration('language');
        $part = null;
        switch ($language) {
            case 'en':
                $part = 'nav_bar_indo'; // Masih belum tahu.
                break;

            case 'id':
                $part = 'nav_bar_indo';
                break;
        }

        (null === $part) or $referer = Log::interpolate($referer, ['language' => $part]);
        $this->browser->headers('Referer', $referer);
    }

    /**
     * Alternative handler for bca_method_post.
     */
    protected function bcaMethodPost()
    {
        $this->browser->options('method', 'POST');
    }

    /**
     * Alternative handler for bca_parse_home_page_anonymous.
     */
    protected function bcaParseHomePageAnonymous()
    {
        switch ($this->target) {
            default:
                $form = $this->configuration('temporary][form');
                $fields = $form->preparePostForm('value(Submit)');
                unset($fields['txtUserId']);
                $fields['value(user_id)'] = $this->username;
                $fields['value(pswd)'] = $this->password;
                $this->addStepFromReference('login_form');
                $this->configuration('menu][bca_login_form][fields', $fields);
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
    protected function bcaParseBalanceInquiryPage()
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

    /**
     * Alternative handler for bca_parse_select_range_form.
     */
    protected function bcaParseSelectRangeForm()
    {
        // BCA memiliki keunikan dalam pencarian mutasi rekening.
        // Untuk harian, hanya bisa 31 hari terakhir dari hari ini.
        // Setelah itu adalah keseluruhan dari awal sampai akhir hari 2 bulan
        // lalu, dan 1 bulan lalu.
        // Ribet amat, yak.
        // 31 hari terakhir itu berarti total hari dari sekarang adalah 31 hari.
        // artinya ada jeda 30 hari antara hari ini dengan hari terakhir.
        // Bila hari ini adalah 2 Februari 2016, maka jika hari terakhir itu
        // 1 Januari 2016, maka tidak valid. tapi jika 2 januari 2016, maka
        // valid.

        $form = $this->configuration('temporary][form');
        $fields = $form->preparePostForm('value(submit1)');

        // Cari tahu rekening.
        $options_rekening = $this->html->find('select[name=value(D1)] > option')->getElements();
        $fields['value(D1)'] = $this->bcaSelectAccountGetValueFromOptionsElement($options_rekening);

        $type = null; // daily, monthly.

        // Check apakah ini revisit.
        $is_revisit = $this->configuration('temporary][revisit_account_statement_page');
        if ($is_revisit) {
            // Copot bulan selanjutnya.
            $_month = key($this->range);
            $month = array_shift($this->range);
            reset($this->range);
            // Jika month adalah bulan ini, maka gunakan pencarian tipe harian.
            // Jika month adalah bulan kemarin, maka gunakan pencarian tipe bulanan.
            $now_month = date('Y-m');
            $last_month = Range::getPrevMonth($now_month);
            if ($_month == $now_month) {
                $type = 'daily';
            }
            elseif ($_month == $last_month) {
                $type = 'monthly';
            }
        }
        else {
            // Bukan revisit, maka:
            $limit = new \DateTime('31 days ago');
            if ($this->range->isSameDay($limit, 'start') || $this->range->comparison($limit, '<', 'start')) {
                $type = 'daily';
                $month = $this->range;
            }
            else {
                $type = 'monthly';
                // Set over range.
                $this->configuration('temporary][over_range', true);
                // Set need to filter.
                $this->configuration('temporary][filter_transaction', true);
                // Simpan original range, karena akan dipecah.
                $this->configuration('temporary][range', $this->range);
                // Atur ulang range.
                $this->range = $this->range->splitPerMonth();
                $_month = key($this->range);
                reset($this->range);
                // Copot bulan pertama.
                $month = array_shift($this->range);
                // $month bisa merupakan start_date di tanggal 13 dan end date
                // di tanggal 31. tapi karena bca selalu di tanggal awal, maka:
                // Rebuild ulang, agar tanggal awal menjadi 1 dan tanggal akhir
                // menjadi last (28/29/30/31.
                $month = Range::create("first day of $_month ~ last day of $_month");
            }
        }

        // Simpan informasi $month, diperlukan untuk
        // method ::bcaConvertDateTransaction()
        $this->configuration('temporary][month', $month);

        // Positioning.
        switch ($type) {
            case 'daily':
                // Pilih field "Mutasi Harian".
                $fields['value(r1)'] = '1';
                unset($fields['value(x)']);
                $fields['value(startDt)'] = $month->format(self::BCA_DATE_FORMAT_DAILY_DATE, 'start');
                $fields['value(startMt)'] = $month->format(self::BCA_DATE_FORMAT_DAILY_MONTH, 'start');
                $fields['value(startYr)'] = $month->format(self::BCA_DATE_FORMAT_DAILY_YEAR, 'start');
                $fields['value(endDt)'] = $month->format(self::BCA_DATE_FORMAT_DAILY_DATE, 'end');
                $fields['value(endMt)'] = $month->format(self::BCA_DATE_FORMAT_DAILY_MONTH, 'end');
                $fields['value(endYr)'] = $month->format(self::BCA_DATE_FORMAT_DAILY_YEAR, 'end');
                $fields['value(fDt)'] = '';
                $fields['value(tDt)'] = '';
                break;

            case 'monthly':
                // Isi field r1
                // Pilih field "Mutasi Bulanan".
                $fields['value(r1)'] = '2';
                // Isi field x
                // Harusnya sudah ada variable $_month (string) dan
                // $month (object Range).
                $now_month = date('Y-m');
                $last_month = Range::getPrevMonth($now_month);
                $two_last_month = Range::getPrevMonth($last_month);
                if ($last_month == $_month) {
                    $fields['value(x)'] = '1';
                }
                elseif ($two_last_month == $_month) {
                    $fields['value(x)'] = '2';
                }
                // Isi field fDt & tDt.
                $fields['value(fDt)'] = $month->format(self::BCA_DATE_FORMAT, 'start');
                $fields['value(tDt)'] = $month->format(self::BCA_DATE_FORMAT, 'end');

                // Anomali BCA.
                // Kasus seperti ini: milih request bulan desember 2015
                // lalu oleh javascriptnya BCA dimodifikasi menjadi tanggal masa
                // depan yakni menjadi 01122016 ~ 31122016. dan saat disubmit
                // ternyata post fieldnya bener-bener tanggal masa depan yakni
                // 01122016 ~ 31122016. Tapi respon yang muncul tetap ke desember
                // 2015 (01122015 ~ 31122015). Hadeh, ke-tidakkonsisten-an ini
                // mengganggu coding.
                // Hack dimulai:
                // Modifikasi, tahun apapun menjadi tahun saat ini.
                $fields['value(fDt)'] = substr($fields['value(fDt)'], 0, -4) . date('Y');
                $fields['value(tDt)'] = substr($fields['value(tDt)'], 0, -4) . date('Y');

                unset($fields['value(startDt)']);
                unset($fields['value(endDt)']);
                unset($fields['value(startMt)']);
                unset($fields['value(endMt)']);
                unset($fields['value(startYr)']);
                unset($fields['value(endYr)']);
                break;

            default:
                // Do something.
                break;
        }
        // Set ke menu.
        $this->configuration("menu][bca_account_statement_page_view][fields", $fields);


    }

    protected function bcaParseRedirectToMain()
    {
        switch ($this->target) {
            default:
                $this->configuration('bca_last_visit', null);
                $this->resetExecute();
                $this->addStepFromReference('home_page');
                break;
        }
    }

    /**
     * Jika tidak ditemukan, maka akan throw ke VisitException.
     */
    protected function bcaSelectAccountGetValueFromOptionsElement(Array $element_options)
    {
        $found = false;
        while ($each = array_shift($element_options)) {
            $extract = ParseHTMLAdvanced::extract($each);
            $text = ParseHTMLAdvanced::extractValueOnly($each);
            if ($text == $this->account) {
                if (isset($extract['a']['value'])) {
                    $found = $extract['a']['value'];
                    break;
                }
            }
        }
        if (false === $found) {
            $this->log->error('Nomor Rekening tidak ditemukan.');
            throw new VisitException;
        }
        return $found;
    }


    protected function bcaFilterTransactionTable($tables)
    {
        $ref = IBank::reference('table_header_account_statement');
        $ref = array_flip($ref);

        $transactions = [];
        while (!empty($tables)) {
            $transaction = [];
            $table = array_shift($tables);
            if (count($table) == 6) {
                list($tgl, $keterangan, $cab, $mutasi_1, $mutasi_2, $saldo) = $table;
                $transaction['date'] = $this->bcaConvertDateTransaction($tgl);
                $keterangan = implode('', $keterangan);
                $keterangan = preg_replace('/<[^>]+>/', ' ', $keterangan);
                $keterangan = preg_replace('/\s\s+/', ' ', $keterangan);
                $keterangan = trim($keterangan);
                $transaction['description'] = $keterangan;
                $transaction['bca_branch'] = $cab;
                $transaction['bca_date'] = $tgl;
                $transaction['amount'] = $mutasi_1;
                $transaction['type'] = $mutasi_2;
                $transaction['balance'] = $saldo;
                $transaction['no'] = null;
                $transaction['id'] = null;
            }
            $transactions[] = array_merge($ref, $transaction);
        }
        return $transactions;
    }

    protected function bcaParseTransactionPage()
    {
        $table = $this->configuration('temporary][table');
        $info = $table->extractTable(true);

        // Buang baris awal.
        array_shift($info);
        $transaction = $this->bcaFilterTransactionTable($info);

        $temporary_result = $this->configuration('temporary][result');
        if (null === $temporary_result) {
            $temporary_result = [];
        }
        $temporary_result = array_merge($temporary_result, $transaction);
        $this->configuration('temporary][result', $temporary_result);
    }

    /**
     * bca_error_login
     */
    protected function bcaErrorLogin()
    {
        $elements = $this->html->find('script')->getElements();
        $error = 'Error login from server.';
        foreach ($elements as $element) {
            if (strpos($element, 'alert(err);') !== false) {
                if (preg_match('/var err=\'(.*)\';/', $element, $m)) {
                    $error = $m[1];
                }
            }
        }
        $this->log->error($error);
        throw new ExecuteException;
    }

    /**
     * bca_check_over_range
     */
    protected function bcaCheckOverRange()
    {
        $finish = false;
        $is_over_range = $this->configuration('temporary][over_range');
        if ($is_over_range) {
            if (empty($this->range)) {
                // Hapus informasi over_range.
                $this->configuration('temporary][over_range', false);
                // Tambah step finishing.
                $finish = true;
            }
            else {
                $this->addStepFromReference('revisit_account_statement_page');
                $this->configuration('temporary][revisit_account_statement_page', true);
            }
        }
        else {
            $finish = true;
        }

        if ($finish) {
            $this->addStepFromReference('transaction_finishing');
        }
    }

    /**
     * bca_transaction_finishing
     */
    protected function bcaTransactionFinishing()
    {
        $temporary_result = $this->configuration('temporary][result');
        $this->result = $temporary_result;

        if ($this->configuration('temporary][filter_transaction')) {
            $this->addStepFromReference('filter_transaction');
        }
        // Hasil BCA selalu ascending.
        if ($this->sort == 'desc') {
            krsort($this->result);
            $this->result = array_values($this->result);
        }
    }

    protected function bcaClearLastVisit()
    {
        $this->configuration('bca_last_visit', null);
        $this->result = 'Logout Success';
    }

    protected function bcaFilterTransaction()
    {
        $result = [];
        // Get original range.
        $range = $this->configuration('temporary][range');
        foreach ($this->result as $each) {
            $date = new \DateTime($each['date']);
            if ($range->isBetween($date)) {
                $result[] = $each;
            }
            unset($date);
        }
        $this->result = $result;
    }

    /**
     *
     * Beberapa contoh string yang ada di BCA adalah
     * - 12/01
     *   artinya 12 januari tahun ini.
     * - PEND
     *   artinya **mungkin** tanggal hari ini, mungkin kemarin, karena ini
     *   muncul pada transaksi yang baru saja terjadi.
     */
    protected function bcaConvertDateTransaction($string)
    {
        $parse = $this->_bcaConvertDateTransaction($string);
        if (false === $parse) {
            $this->log->error('Gagal mengenali date: {name}', ['name' => $string]);
            throw new VisitException;
        }
        if ('' === $parse) {
            return '';
        }
        list($d, $m) = $parse;
        $is_over_range = $this->configuration('temporary][over_range');
        if ($is_over_range) {
            $month = $this->configuration('temporary][month');
            $Y = $month->format('Y', 'end');
        }
        else {
            // Jika tidak over range, maka tipe transaction
            // hanya daily.
            $Y = $this->range->format('Y', 'end');
            $end_month = $this->range->format('m', 'end');
            if ($m == '12' && $end_month == '01') {
                $Y -= 1;
            }
        }
        return "$Y-$m-$d";
    }

    protected function _bcaConvertDateTransaction($string)
    {
        $result = false;
        $string = trim($string);
        if ($string === 'PEND') {
            $result = '';
        }
        elseif (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $string, $m)) {
            // Perlu valid date.
            $result = array($m[1], $m[2]);
        }
        return $result;
    }
}
