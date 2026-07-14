/**
 * =====================================================
 * MONTHLY PAYMENT EXPORT TO LOAN MANAGEMENT TEMPLATE
 *
 * Reads: source payment sheet
 * Writes: sheet "Monthly Payments"
 * Downloads: Excel (.xlsx)
 *
 * Output columns match Laravel payment import template:
 * loan_invoice, payment_date, amount, cash_amount,
 * bank_amount, payoff_amount, payment_method,
 * payment_type, installment_no, schedule_id,
 * currency, exchange_rate, penalty_amount,
 * discount_amount, reference_no, received_by, note
 * =====================================================
 */

var SOURCE_SHEET_CANDIDATES = ['បង់ប្រាក់', 'តារាងសងប្រាក់-', 'Bong Prak', 'Payments Source'];
var OUTPUT_SHEET = 'Monthly Payments';

var LOAN_PREFIX = 'KY-';
var LOAN_DIGITS = 6;

var COL = {
  DATE: 1,
  INVOICE: 2,
  CUST_NAME: 3,
  PHONE: 4,
  MONTHS_TO_PAY: 5,
  PAY_OFF: 6,
  CASH_AMT: 7,
  BANK_AMT: 8,
  CHANNEL: 9,
  TOTAL: 10,
  PRINCIPAL: 11,
  INTEREST: 12,
  PENALTY: 13,
  MISC: 14,
  EMAIL: 15,
  STAFF: 16,
  REF: 17,
  MONTH_NO: 18
};

var OUTPUT_HEADERS = [
  'loan_invoice',
  'payment_date',
  'amount',
  'cash_amount',
  'bank_amount',
  'payoff_amount',
  'payment_method',
  'payment_type',
  'installment_no',
  'schedule_id',
  'currency',
  'exchange_rate',
  'penalty_amount',
  'discount_amount',
  'reference_no',
  'received_by',
  'note'
];

var CHANNEL_MAP = {
  'CASH': 'Cash',
  'ABA': 'ABA',
  'ACLEDA': 'ACLEDA',
  'ACELEDA': 'ACLEDA',
  'WING': 'Wing',
  'E&T': 'E&T',
  'ET': 'E&T',
  'TRUE MONEY': 'True Money',
  'TRUEMONEY': 'True Money',
  'AEON': 'AEON',
  'CARD': 'Card',
  'BANK': 'Bank',
  'OTHER': 'Other'
};

function processBongPrakToPayment() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var srcSheet = findSourceSheet_(ss);

  if (!srcSheet) {
    SpreadsheetApp.getUi().alert(
      'Source sheet not found.\n\nUse one of these sheet names:\n- ' +
      SOURCE_SHEET_CANDIDATES.join('\n- ')
    );
    return;
  }

  var outSheet = prepareOutputSheet_(ss);
  var srcData = srcSheet.getDataRange().getValues();
  var outRows = [];
  var seenKeys = {};
  var stats = {
    totalSourceRows: Math.max(0, srcData.length - 1),
    exportedRows: 0,
    skippedEmptyInvoice: 0,
    skippedInvalidDate: 0,
    skippedZeroAmount: 0,
    skippedDuplicate: 0
  };

  for (var i = 1; i < srcData.length; i++) {
    var row = srcData[i];
    var sourceRowNo = i + 1;

    var invoice = trim_(row[COL.INVOICE - 1]);
    if (!invoice) {
      stats.skippedEmptyInvoice++;
      continue;
    }

    var paymentDate = formatDate_(row[COL.DATE - 1]);
    if (!paymentDate) {
      stats.skippedInvalidDate++;
      continue;
    }

    var cashAmt = round2_(toNumber_(row[COL.CASH_AMT - 1]));
    var bankAmt = round2_(toNumber_(row[COL.BANK_AMT - 1]));
    var payoffAmt = round2_(toNumber_(row[COL.PAY_OFF - 1]));
    var totalAmt = round2_(cashAmt + bankAmt + payoffAmt);
    if (totalAmt <= 0) {
      stats.skippedZeroAmount++;
      continue;
    }

    var loanInvoice = normalizeLoanNumber_(invoice);
    var monthNo = normalizeMonthNo_(row[COL.MONTH_NO - 1], row[COL.MONTHS_TO_PAY - 1]);
    var sourceRef = cleanRef_(row[COL.REF - 1]);
    var receivedBy = trim_(row[COL.STAFF - 1]) || 'Admin';
    var note = buildNote_(row, sourceRowNo, monthNo);
    var channel = normalizeChannel_(row[COL.CHANNEL - 1]);
    var bankMethod = CHANNEL_MAP[channel] || 'Bank';

    if (bankAmt > 0) {
      if (pushPaymentRow_(
        outRows,
        seenKeys,
        loanInvoice,
        paymentDate,
        totalAmt,
        cashAmt,
        bankAmt,
        payoffAmt,
        bankMethod,
        buildReference_(loanInvoice, paymentDate, monthNo, sourceRowNo, 'BANK', sourceRef),
        receivedBy,
        note
      )) {
        stats.exportedRows++;
      } else {
        stats.skippedDuplicate++;
      }
    }

    if (cashAmt > 0) {
      if (pushPaymentRow_(
        outRows,
        seenKeys,
        loanInvoice,
        paymentDate,
        totalAmt,
        cashAmt,
        bankAmt,
        payoffAmt,
        'Cash',
        buildReference_(loanInvoice, paymentDate, monthNo, sourceRowNo, 'CASH', sourceRef),
        receivedBy,
        note
      )) {
        stats.exportedRows++;
      } else {
        stats.skippedDuplicate++;
      }
    }
  }

  if (outRows.length > 0) {
    outSheet.getRange(2, 1, outRows.length, OUTPUT_HEADERS.length).setValues(outRows);
    outSheet.getRange(2, 2, outRows.length, 1).setNumberFormat('yyyy-mm-dd');
    outSheet.getRange(2, 3, outRows.length, 1).setNumberFormat('0.00');
    autoResizeColumns_(outSheet, OUTPUT_HEADERS.length);
  }

  SpreadsheetApp.flush();

  SpreadsheetApp.getUi().alert(
    'Payment template is ready.\n' +
    'Source sheet: ' + srcSheet.getName() + '\n' +
    'Source rows: ' + stats.totalSourceRows + '\n' +
    'Exported rows: ' + stats.exportedRows + '\n' +
    'Skipped empty invoice: ' + stats.skippedEmptyInvoice + '\n' +
    'Skipped invalid date: ' + stats.skippedInvalidDate + '\n' +
    'Skipped zero amount: ' + stats.skippedZeroAmount + '\n' +
    'Skipped duplicate rows: ' + stats.skippedDuplicate
  );

  if (outRows.length > 0) {
    exportPaymentSheetToExcel();
  }
}

function prepareOutputSheet_(ss) {
  var sheet = ss.getSheetByName(OUTPUT_SHEET);

  if (!sheet) {
    sheet = ss.insertSheet(OUTPUT_SHEET);
  } else {
    sheet.clearContents();
    sheet.clearFormats();
  }

  sheet.getRange(1, 1, 1, OUTPUT_HEADERS.length).setValues([OUTPUT_HEADERS]);
  sheet.getRange(1, 1, 1, OUTPUT_HEADERS.length)
    .setFontWeight('bold')
    .setBackground('#1f4e78')
    .setFontColor('#ffffff');
  sheet.setFrozenRows(1);

  return sheet;
}

function pushPaymentRow_(outRows, seenKeys, loanInvoice, paymentDate, amount, cashAmount, bankAmount, payoffAmount, paymentMethod, referenceNo, receivedBy, note) {
  var key = [
    referenceNo || '',
    loanInvoice,
    paymentDate,
    round2_(amount),
    paymentMethod
  ].join('|');

  if (seenKeys[key]) return false;
  seenKeys[key] = true;

  outRows.push([
    loanInvoice,
    paymentDate,
    round2_(amount),
    round2_(cashAmount),
    round2_(bankAmount),
    round2_(payoffAmount),
    paymentMethod,
    'monthly',
    '',
    '',
    'USD',
    1,
    0,
    0,
    referenceNo,
    receivedBy,
    note
  ]);

  return true;
}

function exportPaymentSheetToExcel() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(OUTPUT_SHEET);
  if (!sheet) {
    SpreadsheetApp.getUi().alert('Sheet "' + OUTPUT_SHEET + '" not found.');
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
  var fileName = 'loan-management-payments-import-' + formatToday_() + '.xlsx';

  var htmlOutput = HtmlService.createHtmlOutput(
    '<div style="font-family: Arial, sans-serif; text-align: center; padding: 15px;">' +
    '<p style="color: #2e7d32; font-weight: bold; font-size: 16px;">Payment export is ready.</p>' +
    '<a href="data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' + base64Data + '" ' +
    'download="' + fileName + '" style="background-color: #1f7246; color: white; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 5px;">' +
    'Download Excel (.xlsx)</a></div>'
  ).setWidth(450).setHeight(180);

  SpreadsheetApp.getUi().showModalDialog(htmlOutput, 'Export Payment Data');
}

function buildReference_(loanInvoice, paymentDate, monthNo, sourceRowNo, kind, sourceRef) {
  var parts = [
    'PAY',
    loanInvoice,
    paymentDate,
    'M' + (monthNo || '0'),
    'R' + sourceRowNo,
    kind
  ];

  if (sourceRef) parts.push(sourceRef);
  return parts.join('-');
}

function buildNote_(row, sourceRowNo, monthNo) {
  var parts = [];

  pushNotePart_(parts, 'source_row', sourceRowNo);
  pushNotePart_(parts, 'source_invoice', row[COL.INVOICE - 1]);
  pushNotePart_(parts, 'customer', row[COL.CUST_NAME - 1]);
  pushNotePart_(parts, 'phone', row[COL.PHONE - 1]);
  pushNotePart_(parts, 'months_to_pay', row[COL.MONTHS_TO_PAY - 1]);
  pushNotePart_(parts, 'month_no', monthNo);
  pushNotePart_(parts, 'total', numberText_(row[COL.TOTAL - 1]));
  pushNotePart_(parts, 'principal', numberText_(row[COL.PRINCIPAL - 1]));
  pushNotePart_(parts, 'interest', numberText_(row[COL.INTEREST - 1]));
  pushNotePart_(parts, 'penalty', numberText_(row[COL.PENALTY - 1]));
  pushNotePart_(parts, 'misc', numberText_(row[COL.MISC - 1]));
  pushNotePart_(parts, 'staff', row[COL.STAFF - 1]);
  pushNotePart_(parts, 'email', row[COL.EMAIL - 1]);
  pushNotePart_(parts, 'channel', normalizeChannel_(row[COL.CHANNEL - 1]));
  pushNotePart_(parts, 'source_ref', cleanRef_(row[COL.REF - 1]));

  return parts.join(' | ');
}

function pushNotePart_(parts, label, value) {
  var text = trim_(value);
  if (text !== '') {
    parts.push(label + ': ' + text);
  }
}

function findSourceSheet_(ss) {
  for (var i = 0; i < SOURCE_SHEET_CANDIDATES.length; i++) {
    var sheet = ss.getSheetByName(SOURCE_SHEET_CANDIDATES[i]);
    if (sheet) return sheet;
  }
  return null;
}

function normalizeLoanNumber_(value) {
  var text = trim_(value).replace(/\.0+$/, '');
  if (text === '') return '';

  if (text.toUpperCase().indexOf(LOAN_PREFIX) === 0) {
    return LOAN_PREFIX + normalizeLoanSuffix_(text.substring(LOAN_PREFIX.length));
  }

  return LOAN_PREFIX + normalizeLoanSuffix_(text);
}

function normalizeLoanSuffix_(value) {
  var text = trim_(value).replace(/\.0+$/, '');

  if (/^\d+$/.test(text)) {
    return padLeft_(String(parseInt(text, 10)), LOAN_DIGITS);
  }

  return text.replace(/[^A-Za-z0-9_-]/g, '');
}

function normalizeMonthNo_(monthNoValue, monthsToPayValue) {
  var monthNo = normalizeMonthToken_(monthNoValue);
  if (monthNo !== '') return monthNo;

  var monthsToPay = normalizeMonthToken_(monthsToPayValue);
  if (monthsToPay !== '') return monthsToPay;

  return '0';
}

function normalizeMonthToken_(value) {
  if (value instanceof Date) {
    return Utilities.formatDate(value, Session.getScriptTimeZone(), 'yyyyMM');
  }

  var text = trim_(value);
  if (text === '') return '';

  if (/^\d+(\.0+)?$/.test(text)) {
    return String(parseInt(text, 10));
  }

  var normalizedText = text.replace(/\s*\([^)]+\)\s*$/, '');
  var parsed = new Date(normalizedText);
  if (!isNaN(parsed.getTime())) {
    return Utilities.formatDate(parsed, Session.getScriptTimeZone(), 'yyyyMM');
  }

  return text.replace(/[^A-Za-z0-9_-]/g, '');
}

function normalizeChannel_(value) {
  return trim_(value).toUpperCase().replace(/\s+/g, ' ');
}

function formatDate_(value) {
  var d = value instanceof Date ? value : new Date(value);
  if (isNaN(d.getTime())) return '';
  return Utilities.formatDate(d, Session.getScriptTimeZone(), 'yyyy-MM-dd');
}

function toNumber_(value) {
  var n = parseFloat(String(value).replace(/,/g, ''));
  return isNaN(n) ? 0 : n;
}

function round2_(n) {
  return Math.round(Number(n || 0) * 100) / 100;
}

function trim_(value) {
  return value === null || value === undefined ? '' : String(value).trim();
}

function cleanRef_(value) {
  return trim_(value).replace(/[^A-Za-z0-9_-]/g, '');
}

function numberText_(value) {
  var number = toNumber_(value);
  return number > 0 ? String(round2_(number)) : '';
}

function padLeft_(value, width) {
  var text = String(value);
  while (text.length < width) {
    text = '0' + text;
  }
  return text;
}

function autoResizeColumns_(sheet, totalColumns) {
  for (var c = 1; c <= totalColumns; c++) {
    sheet.autoResizeColumn(c);
  }
}

function formatToday_() {
  return Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd');
}

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('LOAN EXPORT')
    .addItem('1. Process Payment To Template', 'processBongPrakToPayment')
    .addItem('2. Download Payment As Excel', 'exportPaymentSheetToExcel')
    .addToUi();
}
