/**
 * =====================================================
 * INSTALLMENT TO LOAN IMPORT TEMPLATE - Google Apps Script
 * Reads:  sheet "installment"
 * Writes: sheet "loans-import-template" and downloads Excel (.xlsx)
 *
 * Output columns are aligned with LoanManagement loan import:
 * loan_number, location_id, location_name, customer_id, customer_code,
 * customer_name, khmer_name, customer_phone, alternate_phone, email,
 * id_number, gender, date_of_birth, address, province, district,
 * commune, village, family_contact_name, family_contact_phone,
 * spouse_name, spouse_phone, workplace, monthly_income, product_name,
 * imei_or_serial, qty, unit_price, product_price, principal_amount,
 * interest_amount, total_amount, paid_amount, balance_amount,
 * down_payment, down_payment_cash, down_payment_bank, paid_date,
 * payment_method, reference_number, installment_count, payment_frequency,
 * loan_date, first_due_date, maturity_date, status, currency,
 * collection_status, risk_level, assigned_collector_id,
 * assigned_collection_team, days_past_due, overdue_bucket,
 * next_followup_at, ptp_date, ptp_amount, note
 * =====================================================
 */

var INSTALLMENT_SOURCE_SHEET = 'installment';
var LOAN_OUTPUT_SHEET = 'loans-import-template';
var LOAN_PREFIX = 'KY-';
var CUSTOMER_CODE_PREFIX = 'CUS-';

var DEFAULTS = {
  locationId: '',
  locationName: '',
  status: 'active',
  currency: 'USD',
  collectionStatus: 'normal',
  riskLevel: 'low',
  paymentFrequency: 'monthly',
  assignedCollectorId: '',
  assignedCollectionTeam: ''
};

var INSTALLMENT_COL = {
  INVOICE: 2,
  LOAN_DATE: 3,
  CUSTOMER_NAME: 4,
  CUSTOMER_PHONE: 5,
  ID_NUMBER: 6,
  ADDRESS_1: 7,
  ADDRESS_2: 8,
  ADDRESS_3: 9,
  ADDRESS_4: 10,
  PRODUCT_NAME: 12,
  QTY: 13,
  UNIT_PRICE: 14,
  PRODUCT_PRICE: 15,
  CASH_DOWN_PAYMENT: 16,
  ABA_DOWN_PAYMENT: 17,
  ACLEDA_DOWN_PAYMENT: 18,
  WING_DOWN_PAYMENT: 19,
  ET_DOWN_PAYMENT: 20,
  CARD_DOWN_PAYMENT: 21,
  WAIDO_DOWN_PAYMENT: 22,
  DOWN_PAYMENT: 23,
  PRINCIPAL_AMOUNT: 24,
  FIRST_DUE_DATE: 26,
  INSTALLMENT_COUNT: 27,
  IMEI_OR_SERIAL: 44,
  EMAIL: 45,
  NOTE: 46
};

var LOAN_HEADERS = [
  'loan_number',
  'location_id',
  'location_name',
  'customer_id',
  'customer_code',
  'customer_name',
  'khmer_name',
  'customer_phone',
  'alternate_phone',
  'email',
  'id_number',
  'gender',
  'date_of_birth',
  'address',
  'province',
  'district',
  'commune',
  'village',
  'family_contact_name',
  'family_contact_phone',
  'spouse_name',
  'spouse_phone',
  'workplace',
  'monthly_income',
  'product_name',
  'imei_or_serial',
  'qty',
  'unit_price',
  'product_price',
  'principal_amount',
  'interest_amount',
  'total_amount',
  'paid_amount',
  'balance_amount',
  'down_payment',
  'down_payment_cash',
  'down_payment_bank',
  'paid_date',
  'payment_method',
  'reference_number',
  'installment_count',
  'payment_frequency',
  'loan_date',
  'first_due_date',
  'maturity_date',
  'status',
  'currency',
  'collection_status',
  'risk_level',
  'assigned_collector_id',
  'assigned_collection_team',
  'days_past_due',
  'overdue_bucket',
  'next_followup_at',
  'ptp_date',
  'ptp_amount',
  'note'
];

var DOWN_PAYMENT_METHODS = [
  { name: 'cash', col: INSTALLMENT_COL.CASH_DOWN_PAYMENT, bucket: 'cash' },
  { name: 'aba', col: INSTALLMENT_COL.ABA_DOWN_PAYMENT, bucket: 'bank' },
  { name: 'acleda', col: INSTALLMENT_COL.ACLEDA_DOWN_PAYMENT, bucket: 'bank' },
  { name: 'wing', col: INSTALLMENT_COL.WING_DOWN_PAYMENT, bucket: 'bank' },
  { name: 'et', col: INSTALLMENT_COL.ET_DOWN_PAYMENT, bucket: 'bank' },
  { name: 'card', col: INSTALLMENT_COL.CARD_DOWN_PAYMENT, bucket: 'bank' },
  { name: 'waido', col: INSTALLMENT_COL.WAIDO_DOWN_PAYMENT, bucket: 'bank' }
];

function mapInstallmentToLoanTemplate() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var srcSheet = ss.getSheetByName(INSTALLMENT_SOURCE_SHEET);
  if (!srcSheet) {
    SpreadsheetApp.getUi().alert('Sheet "' + INSTALLMENT_SOURCE_SHEET + '" not found.');
    return;
  }

  var outSheet = prepareLoanOutputSheet_(ss);
  var srcData = srcSheet.getDataRange().getValues();
  var outRows = [];
  var seenLoanNumbers = {};

  for (var i = 1; i < srcData.length; i++) {
    var row = srcData[i];
    var invoice = trim_(row[INSTALLMENT_COL.INVOICE - 1]);
    if (!invoice) continue;

    var loanNumber = LOAN_PREFIX + padInvoice_(invoice);
    if (seenLoanNumbers[loanNumber]) continue;

    var principalAmount = round2_(toNumber_(row[INSTALLMENT_COL.PRINCIPAL_AMOUNT - 1]));
    if (principalAmount <= 0) continue;

    var customerName = trim_(row[INSTALLMENT_COL.CUSTOMER_NAME - 1]);
    if (!customerName) continue;

    var qty = toNumberOrDefault_(row[INSTALLMENT_COL.QTY - 1], 1);
    var unitPrice = round2_(toNumber_(row[INSTALLMENT_COL.UNIT_PRICE - 1]));
    var productPrice = round2_(toNumber_(row[INSTALLMENT_COL.PRODUCT_PRICE - 1]));
    var interestAmount = deriveInterestAmount_(principalAmount, productPrice);
    var totalAmount = productPrice > 0 ? productPrice : round2_(principalAmount + interestAmount);
    var downPayment = summarizeDownPayment_(row);
    var paidAmount = downPayment.total;
    var balanceAmount = round2_(Math.max(0, totalAmount - paidAmount));
    var loanDate = formatDate_(row[INSTALLMENT_COL.LOAN_DATE - 1]);
    var firstDueDate = formatDate_(row[INSTALLMENT_COL.FIRST_DUE_DATE - 1]);

    outRows.push([
      loanNumber,
      DEFAULTS.locationId,
      DEFAULTS.locationName,
      '',
      CUSTOMER_CODE_PREFIX + padInvoice_(invoice),
      customerName,
      customerName,
      trim_(row[INSTALLMENT_COL.CUSTOMER_PHONE - 1]),
      '',
      trim_(row[INSTALLMENT_COL.EMAIL - 1]),
      trim_(row[INSTALLMENT_COL.ID_NUMBER - 1]),
      '',
      '',
      buildAddress_(row),
      trim_(row[INSTALLMENT_COL.ADDRESS_4 - 1]),
      trim_(row[INSTALLMENT_COL.ADDRESS_3 - 1]),
      trim_(row[INSTALLMENT_COL.ADDRESS_2 - 1]),
      trim_(row[INSTALLMENT_COL.ADDRESS_1 - 1]),
      '',
      '',
      '',
      '',
      '',
      '',
      trim_(row[INSTALLMENT_COL.PRODUCT_NAME - 1]),
      trim_(row[INSTALLMENT_COL.IMEI_OR_SERIAL - 1]),
      qty,
      unitPrice,
      productPrice,
      principalAmount,
      interestAmount,
      totalAmount,
      paidAmount,
      balanceAmount,
      downPayment.total,
      downPayment.cash,
      downPayment.bank,
      loanDate,
      downPayment.method,
      buildDownPaymentReference_(loanNumber),
      toIntegerOrBlank_(row[INSTALLMENT_COL.INSTALLMENT_COUNT - 1]),
      DEFAULTS.paymentFrequency,
      loanDate,
      firstDueDate,
      '',
      DEFAULTS.status,
      DEFAULTS.currency,
      DEFAULTS.collectionStatus,
      DEFAULTS.riskLevel,
      DEFAULTS.assignedCollectorId,
      DEFAULTS.assignedCollectionTeam,
      0,
      '',
      '',
      '',
      '',
      buildLoanNote_(row)
    ]);

    seenLoanNumbers[loanNumber] = true;
  }

  if (outRows.length > 0) {
    outSheet.getRange(2, 1, outRows.length, LOAN_HEADERS.length).setValues(outRows);
  }

  autoResizeColumns_(outSheet, LOAN_HEADERS.length);
  SpreadsheetApp.flush();

  SpreadsheetApp.getUi().alert(
    'Loan import template is ready.\n' +
    'Valid rows: ' + outRows.length + '\n' +
    'Sheet: ' + LOAN_OUTPUT_SHEET
  );
}

function exportLoanTemplateToExcel() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(LOAN_OUTPUT_SHEET);
  if (!sheet) {
    SpreadsheetApp.getUi().alert('Sheet "' + LOAN_OUTPUT_SHEET + '" not found.');
    return;
  }

  var url = 'https://docs.google.com/spreadsheets/d/' + ss.getId() + '/export?format=xlsx&gid=' + sheet.getSheetId();
  var token = ScriptApp.getOAuthToken();
  var response = UrlFetchApp.fetch(url, {
    headers: { Authorization: 'Bearer ' + token },
    muteHttpExceptions: true
  });

  var statusCode = response.getResponseCode();
  if (statusCode < 200 || statusCode >= 300) {
    SpreadsheetApp.getUi().alert('Excel export failed. HTTP ' + statusCode);
    return;
  }

  var blob = response.getBlob();
  var base64Data = Utilities.base64Encode(blob.getBytes());
  var fileName = 'Loan_Import_' + formatToday_() + '.xlsx';

  var htmlOutput = HtmlService.createHtmlOutput(
    '<div style="font-family: Arial, sans-serif; text-align: center; padding: 15px;">' +
    '<p style="color: #2e7d32; font-weight: bold; font-size: 16px;">Loan export is ready.</p>' +
    '<a href="data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' + base64Data + '" ' +
    'download="' + fileName + '" style="background-color: #1f7246; color: white; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 5px;">' +
    'Download Excel (.xlsx)</a></div>'
  ).setWidth(450).setHeight(180);

  SpreadsheetApp.getUi().showModalDialog(htmlOutput, 'Export Loan Data');
}

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('LOAN IMPORT')
    .addItem('1. Process Installment to Loan Template', 'mapInstallmentToLoanTemplate')
    .addItem('2. Download Loan Template as Excel', 'exportLoanTemplateToExcel')
    .addToUi();
}

function prepareLoanOutputSheet_(ss) {
  var sheet = ss.getSheetByName(LOAN_OUTPUT_SHEET);
  if (!sheet) {
    sheet = ss.insertSheet(LOAN_OUTPUT_SHEET);
  } else {
    sheet.clearContents();
  }

  sheet.getRange(1, 1, 1, LOAN_HEADERS.length).setValues([LOAN_HEADERS]);
  sheet.setFrozenRows(1);
  return sheet;
}

function summarizeDownPayment_(row) {
  var cash = 0;
  var bank = 0;
  var methodNames = [];

  for (var i = 0; i < DOWN_PAYMENT_METHODS.length; i++) {
    var item = DOWN_PAYMENT_METHODS[i];
    var amount = round2_(toNumber_(row[item.col - 1]));
    if (amount <= 0) continue;

    if (item.bucket === 'cash') {
      cash += amount;
    } else {
      bank += amount;
    }
    methodNames.push(item.name);
  }

  var total = round2_(cash + bank);
  var fallbackTotal = round2_(toNumber_(row[INSTALLMENT_COL.DOWN_PAYMENT - 1]));
  if (total <= 0 && fallbackTotal > 0) {
    total = fallbackTotal;
    cash = fallbackTotal;
    methodNames = ['cash'];
  }

  return {
    total: total,
    cash: round2_(cash),
    bank: round2_(bank),
    method: summarizeMethodNames_(methodNames, cash, bank)
  };
}

function summarizeMethodNames_(methodNames, cash, bank) {
  if (methodNames.length === 0) return '';
  if (methodNames.length === 1) return methodNames[0];
  if (cash > 0 && bank > 0) return 'Mixed';
  return methodNames.join('+');
}

function buildDownPaymentReference_(loanNumber) {
  return 'IMP-DOWN-' + loanNumber;
}

function buildLoanNote_(row) {
  var parts = [];
  var address = buildAddress_(row);
  if (address) parts.push('address: ' + address);

  var sourceNote = trim_(row[INSTALLMENT_COL.NOTE - 1]);
  if (sourceNote) parts.push('source_note: ' + sourceNote);

  return parts.join(' | ');
}

function buildAddress_(row) {
  return [
    trim_(row[INSTALLMENT_COL.ADDRESS_1 - 1]),
    trim_(row[INSTALLMENT_COL.ADDRESS_2 - 1]),
    trim_(row[INSTALLMENT_COL.ADDRESS_3 - 1]),
    trim_(row[INSTALLMENT_COL.ADDRESS_4 - 1])
  ].filter(function(value) {
    return value !== '';
  }).join(', ');
}

function deriveInterestAmount_(principalAmount, productPrice) {
  if (productPrice > principalAmount) {
    return round2_(productPrice - principalAmount);
  }
  return 0;
}

function autoResizeColumns_(sheet, count) {
  for (var i = 1; i <= count; i++) {
    sheet.autoResizeColumn(i);
  }
}

function padInvoice_(value) {
  var s = String(value).trim().replace(/\.0+$/, '');
  while (s.length < 6) s = '0' + s;
  return s;
}

function formatDate_(value) {
  if (value === '' || value === null || value === undefined) return '';
  var d = value instanceof Date ? value : new Date(value);
  if (isNaN(d.getTime())) return '';
  return Utilities.formatDate(d, Session.getScriptTimeZone(), 'yyyy-MM-dd');
}

function formatToday_() {
  return Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd');
}

function toNumber_(value) {
  var cleaned = String(value === null || value === undefined ? '' : value)
    .replace(/,/g, '')
    .replace(/[^0-9.\-]/g, '');
  var n = parseFloat(cleaned);
  return isNaN(n) ? 0 : n;
}

function toNumberOrDefault_(value, fallback) {
  var n = toNumber_(value);
  return n > 0 ? n : fallback;
}

function round2_(n) {
  return Math.round(n * 100) / 100;
}

function toIntegerOrBlank_(value) {
  var n = parseInt(String(value === null || value === undefined ? '' : value).replace(/[^0-9\-]/g, ''), 10);
  return isNaN(n) ? '' : n;
}

function trim_(value) {
  return value === null || value === undefined ? '' : String(value).trim();
}
