<?php
/**
 * RayPay payment plugin
 *
 * @developer hanieh729
 * @publisher RayPay
 * @package RSForm Pro
 * @subpackage payment
 * @copyright (C) 2021 RayPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://raypay.ir
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
class plgSystemRSFPRayPay extends JPlugin
{
  var $componentId = 800;

  var $componentValue = 'raypay';

  /**
   * plgSystemRSFPRayPay constructor.
   * @param $subject
   * @param $config
   * @param Http|null $http
   */
  public function __construct(&$subject, $config, Http $http = null)
  {
    $this->http = $http ?: HttpFactory::getHttp();
    parent::__construct($subject, $config);
    $this->newComponents = array(800);
  }


  function rsfp_bk_onAfterShowComponents()
  {
    $lang = JFactory::getLanguage();
    $lang->load('plg_system_rsfpraypay');
    $formId = JRequest::getInt('formId');
    $link = "displayTemplate('" . $this->componentId . "')";
    if ($components = RSFormProHelper::componentExists($formId, $this->componentId))
      $link = "displayTemplate('" . $this->componentId . "', '" . $components[0] . "')";
    ?>
      <li class="rsform_navtitle"><?php echo 'درگاه رای پی'; ?></li>
      <li><a href="javascript: void(0);" onclick="<?php echo $link; ?>;return false;"
             id="rsfpc<?php echo $this->componentId; ?>"><span
                      id="RAYPAY"><?php echo JText::_('اضافه کردن درگاه رای پی'); ?></span></a></li>
    <?php
  }

  /**
   * @param $items
   * @param $formId
   */
  function rsfp_getPayment(&$items, $formId)
  {
    if ($components = RSFormProHelper::componentExists($formId, $this->componentId)) {
      $data = RSFormProHelper::getComponentProperties($components[0]);
      $item = new stdClass();
      $item->value = $this->componentValue;
      $item->text = $data['LABEL'] . '(پرداخت امن با رای پی)';

      //JURI::root(true).'/plugins/system/rsfpraypay/assets/images/logo.png
      $items[] = $item;
    }
  }

  /**
   * @param array $args
   */
  function rsfp_bk_onAfterCreateComponentPreview($args = array())
  {
    if ($args['ComponentTypeName'] == 'raypay') {
      $args['out'] = '<td>&nbsp;raypay</td>';
      $args['out'] .= '<td><img src=' . JURI::root(true) . '/plugins/system/rsfpraypay/assets/images/logo.png />' . $args['data']['LABEL'] . '</td>';
    }
  }

  /**
   * @param $tabs
   */
  function rsfp_bk_onAfterShowConfigurationTabs($tabs)
  {
    $lang = JFactory::getLanguage();
    $lang->load('plg_system_rsfpraypay');
    $tabs->addTitle('تنظیمات درگاه رای پی', 'form-TRANGELRAYPAY');
    $tabs->addContent($this->raypayConfigurationScreen());
  }

  /**
   * @param $payValue
   * @param $formId
   * @param $SubmissionId
   * @param $price
   * @param $products
   * @param $code
   */
  function rsfp_doPayment($payValue, $formId, $SubmissionId, $price, $products, $code)
  {
    $components = RSFormProHelper::componentExists($formId, $this->componentId);
    $data = RSFormProHelper::getComponentProperties($components[0]);
    $app = JFactory::getApplication();

    if ($data['TOTAL'] == 'YES') {
      $price = (int)$_POST['form']['rsfp_Total'];
    } elseif ($data['TOTAL'] == 'NO') {
      if ($data['FIELDNAME'] == 'Select the desired field') {
        $msg = 'فیلدی برای قیمت انتخاب نشده است.';
        $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
        $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
      }
      $price = $_POST['form'][$data['FIELDNAME']];
    }

    if (is_array($price))
      $price = (int)array_sum($price);

    if (!$price) {
      $msg = 'مبلغی وارد نشده است';
      $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
      $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
    }

    $currency = RSFormProHelper::getConfig('raypay.currency');
    $price = $this->rsfp_raypay_get_amount($price, $currency);

    // execute only for our plugin
    if ($payValue != $this->componentValue) return;
    $tax = RSFormProHelper::getConfig('raypay.tax.value');
    if ($tax)
      $nPrice = round($tax, 0) + round($price, 0);
    else
      $nPrice = round($price, 0);

    if ($nPrice > 100) {
      $user_id = RSFormProHelper::getConfig('raypay.user_id');
      $acceptor_code = RSFormProHelper::getConfig('raypay.acceptor_code');
      $amount = $nPrice;
      $desc = 'پرداخت سفارش شماره: ' . $formId;
      $invoice_id             = round(microtime(true) * 1000);
      $callback = JURI::root() . 'index.php?option=com_rsform&task=plugin&plugin_task=raypay.notify&code=' . $code . '&order_id=' . $formId . '&';
      if (empty($amount)) {
        $msg = 'واحد پول انتخاب شده پشتیبانی نمی شود.';
        $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
        $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
      }

        $data = array(
            'amount'       => strval($amount),
            'invoiceID'    => strval($invoice_id),
            'userID'       => $user_id,
            'redirectUrl'  => $callback,
            'factorNumber' => strval($formId),
            'acceptorCode' => $acceptor_code,
            'comment'      => $desc
        );

        $url  = 'http://185.165.118.211:14000/raypay/api/v1/Payment/getPaymentTokenWithUserID';
        $options = array('Content-Type' => 'application/json');
        $result = $this->http->post($url, json_encode($data, true), $options);
        $result = json_decode($result->body);
        $http_status = $result->StatusCode;

      //save raypay invoice id in db
      $db = JFactory::getDBO();
      $sql = 'INSERT INTO `#__rsform_submission_values` (FormId, SubmissionId, FieldName,FieldValue) VALUES (' . $formId . ',' . $SubmissionId . ',"raypay_id","' . $invoice_id . '")';
      $db->setQuery($sql);
      $db->execute();


      if ($http_status != 200 || empty($result) || empty($result->Data)) {
        $msg         = sprintf('خطا هنگام ایجاد تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
        $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
        $this->updateAfterEvent($formId, $SubmissionId, $this->otherStatusMessages($result->status));
        $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
      }

        $access_token = $result->Data->Accesstoken;
        $terminal_id  = $result->Data->TerminalID;


        echo '<p style="color:#ff0000; font:18px Tahoma; direction:rtl;">در حال اتصال به درگاه بانکی. لطفا صبر کنید ...</p>';
        echo '<form name="frmRayPayPayment" method="post" action=" https://mabna.shaparak.ir:8080/Pay ">';
        echo '<input type="hidden" name="TerminalID" value="' . $terminal_id . '" />';
        echo '<input type="hidden" name="token" value="' . $access_token . '" />';
        echo '<input class="submit" type="submit" value="پرداخت" /></form>';
        echo '<script>document.frmRayPayPayment.submit();</script>';
        exit();
    }
    else {

      $msg = 'مبلغ وارد شده کمتر از ۱۰۰۰۰ ریال می باشد';
      $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
      $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
    }
  }

  /**
   * @return |null
   */
  function rsfp_f_onSwitchTasks()
  {
    if (JRequest::getVar('plugin_task') == 'raypay.notify') {
      $app = JFactory::getApplication();
      $jinput = $app->input;

      $invoiceId = $jinput->get->get('?invoiceID', '', 'STRING');
      $orderId = $jinput->get->get('order_id', '', 'STRING');
      $code = $jinput->get->get('code', '', 'STRING');
      $db = JFactory::getDBO();
      $db->setQuery("SELECT SubmissionId FROM #__rsform_submissions s WHERE s.FormId='" . $orderId . "' AND MD5(CONCAT(s.SubmissionId,s.DateSubmitted)) = '" . $db->escape($code) . "'");
      $SubmissionId = $db->loadResult();
      $components = RSFormProHelper::componentExists($orderId, $this->componentId);
      $data = RSFormProHelper::getComponentProperties($components[0]);
      $app = JFactory::getApplication();

      if ($data['TOTAL'] == 'YES') {
        $fieldname="rsfp_Total";
      } elseif ($data['TOTAL'] == 'NO') {
        $fieldname=$data['FIELDNAME'];
      }

      $price = round($this::getPayerPrice($orderId, $SubmissionId,$fieldname), 0);

      //convert to currency
      $currency = RSFormProHelper::getConfig('raypay.currency');
      $price = $this->rsfp_raypay_get_amount($price, $currency);


      if (!empty($invoiceId) && !empty($orderId)) {

          $data = array('order_id' => $orderId);
          $url = 'http://185.165.118.211:14000/raypay/api/v1/Payment/checkInvoice?pInvoiceID=' . $invoiceId;;
          $options = array('Content-Type' => 'application/json');
          $result = $this->http->post($url, json_encode($data, true), $options);
          $result = json_decode($result->body);
          $http_status = $result->StatusCode;

          //http error
          if ($http_status != 200) {
              $msg = sprintf('خطا هنگام استعلام وضعیت تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
            $this->updateAfterEvent($orderId, $SubmissionId, $msg);
            $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $orderId;
            $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
          }

          $state           = $result->Data->State;
          $verify_order_id = $result->Data->FactorNumber;
          $verify_amount   = $result->Data->Amount;

          if ($state === 1)
          {
              $verify_status = 'پرداخت موفق';
          }
          else
          {
              $verify_status = 'پرداخت ناموفق';
          }

          //failed verify
          if ( empty($verify_order_id) || empty($verify_amount) || $state !== 1) {
            $msg  = 'پرداخت ناموفق بوده است. شناسه ارجاع بانکی رای پی : ' . $invoiceId;
            $this->updateAfterEvent($orderId, $SubmissionId, $msg);
            $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $orderId;
            $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');

            //successful verify
          } else {
            $mainframe = JFactory::getApplication();
            $mainframe->triggerEvent('rsfp_afterConfirmPayment', array($SubmissionId));
            $msgForSaveDataTDataBase = ' پرداخت موفق از طریق درگاه پرداخت رای پی . شناسه ارجاع بانکی : ' . $invoiceId;
            $this->updateAfterEvent($orderId, $SubmissionId, $msgForSaveDataTDataBase);
            $msg  = 'پرداخت شما با موفقیت انجام شد.';
            $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $orderId;

            $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'success');
          }
      } else {

        $msg = 'خطا هنگام بازگشت از درگاه پرداخت';
        $this->updateAfterEvent($orderId, $SubmissionId, $msg);
        $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $orderId;
        $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
      }

    } else {
      return NULL;
    }
  }

  /**
   * @return false|string
   */
  function raypayConfigurationScreen()
  {
    ob_start();
    ?>
      <div id="page-raypay" class="com-rsform-css-fix">
          <table class="admintable">
              <tr>
                  <td width="200" style="width: 200px;" align="right" class="key"><label
                              for="user_id"><?php echo 'شناسه کاربری'; ?></label></td>
                  <td><input type="text" name="rsformConfig[raypay.user_id]"
                             value="<?php echo RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('raypay.user_id')); ?>"
                             size="100" maxlength="64"></td>
              </tr>
              <tr>
                  <td width="200" style="width: 200px;" align="right" class="key"><label
                              for="acceptor_code"><?php echo 'کد پذیرنده'; ?></label></td>
                  <td><input type="text" name="rsformConfig[raypay.acceptor_code]"
                             value="<?php echo RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('raypay.acceptor_code')); ?>"
                             size="100" maxlength="64"></td>
              </tr>
              <tr>
                  <td width="200" style="width: 200px;" align="right" class="key">
                      <label><?php echo 'واحد پول'; ?></label></td>
                  <td>
                    <?php
                    echo(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('raypay.currency')));

                    ?>
                      <select name="rsformConfig[raypay.currency]">
                          <option value="RIAL"<?php echo(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('raypay.currency')) == 'RIAL' ? 'selected="selected"' : ""); ?>>
                              ریال
                          </option>
                          <option value="IRT"<?php echo(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('raypay.currency')) == 'IRT' ? 'selected="selected"' : ""); ?>>
                              تومان
                          </option>
                      </select>
                  </td>
              </tr>
          </table>
      </div>

    <?php

    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
  }

  /**
   * @param $formId
   * @param $SubmissionId
   * @return mixed
   */
  function getPayerMobile($formId, $SubmissionId)
  {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true);
    $query->select('FieldValue')
      ->from($db->qn('#__rsform_submission_values'));
    $query->where(
      $db->qn('FormId') . ' = ' . $db->q($formId)
      . ' AND ' .
      $db->qn('SubmissionId') . ' = ' . $db->q($SubmissionId)
      . ' AND ' .
      $db->qn('FieldName') . ' = ' . $db->q('mobile')
    );
    $db->setQuery((string)$query);
    $result = $db->loadResult();
    return $result;
  }

  /**
   * @param $formId
   * @param $SubmissionId
   * @param $fieldName
   * @return int
   */
  function getPayerPrice($formId, $SubmissionId, $fieldName)
  {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true);
    $query->select('FieldValue')
      ->from($db->qn('#__rsform_submission_values'));
    $query->where(
      $db->qn('FormId') . ' = ' . $db->q($formId)
      . ' AND ' .
      $db->qn('SubmissionId') . ' = ' . $db->q($SubmissionId)
      . ' AND ' .
      $db->qn('FieldName') . ' = ' . $db->q($fieldName)
    );
    $db->setQuery((string)$query);
    $result = $db->loadResult();

    return (int)$result;
  }


  /**
   * @param $amount
   * @param $currency
   * @return float|int
   */
  function rsfp_raypay_get_amount($amount, $currency)
  {
    switch (strtolower($currency)) {
      case strtolower('IRR'):
      case strtolower('RIAL'):
        return $amount;

      case strtolower('تومان ایران'):
      case strtolower('تومان'):
      case strtolower('IRT'):
      case strtolower('Iranian_TOMAN'):
      case strtolower('Iran_TOMAN'):
      case strtolower('Iranian-TOMAN'):
      case strtolower('Iran-TOMAN'):
      case strtolower('TOMAN'):
      case strtolower('Iran TOMAN'):
      case strtolower('Iranian TOMAN'):
        return $amount * 10;

      case strtolower('IRHR'):
        return $amount * 1000;

      case strtolower('IRHT'):
        return $amount * 10000;

      default:
        return 0;
    }
  }

  /**
   * @param $formId
   * @param $SubmissionId
   * @param $msg
   * @return bool
   */
  public function updateAfterEvent($formId, $SubmissionId, $msg)
  {
    if (!$SubmissionId) {
      return false;
    }
    $db = JFactory::getDBO();
    $msg = "raypay: $msg";
    $db->setQuery("UPDATE #__rsform_submission_values sv SET sv.FieldValue=1 WHERE sv.FieldName='_STATUS' AND sv.FormId='" . $formId . "' AND sv.SubmissionId = '" . $SubmissionId . "'");
    $db->execute();
    $db->setQuery("UPDATE #__rsform_submission_values sv SET sv.FieldValue='" . $msg . "'  WHERE sv.FieldValue='raypay' AND sv.FormId='" . $formId . "' AND sv.SubmissionId = '" . $SubmissionId . "'");
    $db->execute();
  }

}
