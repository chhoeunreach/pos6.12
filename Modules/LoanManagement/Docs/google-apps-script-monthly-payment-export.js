/**
 * =====================================================
 * MONTHLY PAYMENT EXPORT TO EXCEL - Google Apps Script
 * Reads: sheet "បង់ប្រាក់" or "តារាងសងប្រាក់-"
 * Writes: sheet "payment" and downloads Excel (.xlsx)
 *
 * Output columns are aligned with LoanManagement payment import:
 * loan_number, loan_id, schedule_id, payment_type, amount, paid_date,
 * payment_method, currency, exchange_rate, reference_number, note
 * =====================================================
 */

var SOURCE_SHEET_CANDIDATES = ['បង់ប្រាក់', 'តារាងសងប្រាក់-'];
var OUTPUT_SHEET = 'payment';
var LOAN_PREFIX = 'KY-';
var LOAN_DIGITS = 6;
var DEFAULT_CURRENCY = 'USD';
var DEFAULT_EXCHANGE_RATE = 1;

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
  'loan_number',
  'loan_id',
  'schedule_id',
  'payment_type',
  'amount',
  'paid_date',
  'payment_method',
  'currency',
  'exchange_rate',
  'reference_number',
  'note'
];

var CHANNEL_MAP = {
  'CASH': 'cash',
  'ABA': 'custom_pay_2',
  'ACLEDA': 'custom_pay_3',
  'ACELEDA': 'custom_pay_3',
  'WING': 'custom_pay_1',
  'E&T': 'custom_pay_6',
  'ET': 'custom_pay_6',
  'TRUE MONEY': 'custom_pay_4',
  'TRUEMONEY': 'custom_pay_4',
  'AEON': 'custom_pay_5',
  'CARD': 'card',
  'BANK': 'bank_transfer',
  'OTHER': 'other'
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

    var paidDate = formatDate_(row[COL.DATE - 1]);
    if (!paidDate) {
      stats.skippedInvalidDate++;
      continue;
    }

    var cashAmt = round2_(toNumber_(row[COL.CASH_AMT - 1]));
    var bankAmt = round2_(toNumber_(row[COL.BANK_AMT - 1]));
    if (cashAmt <= 0 && bankAmt <= 0) {
      stats.skippedZeroAmount++;
      continue;
    }

    var loanNumber = normalizeLoanNumber_(invoice);
    var monthNo = normalizeMonthNo_(row[COL.MONTH_NO - 1], row[COL.MONTHS_TO_PAY - 1]);
    var sourceRef = cleanRef_(row[COL.REF - 1]);
    var paymentType = resolvePaymentType_(row);
    var note = buildNote_(row, sourceRowNo, monthNo);
    var channel = normalizeChannel_(row[COL.CHANNEL - 1]);
    var bankMethod = CHANNEL_MAP[channel] || 'bank_transfer';

    if (bankAmt > 0) {
      if (pushPaymentRow_(
        outRows,
        seenKeys,
        loanNumber,
        paymentType,
        bankAmt,
        paidDate,
        bankMethod,
        buildReference_(loanNumber, paidDate, monthNo, sourceRowNo, 'BANK', sourceRef),
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
        loanNumber,
        paymentType,
        cashAmt,
        paidDate,
        'cash',
        buildReference_(loanNumber, paidDate, monthNo, sourceRowNo, 'CASH', sourceRef),
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
  var fileName = 'Payment_Import_' + formatToday_() + '.xlsx';

  var htmlOutput = HtmlService.createHtmlOutput(
    '<div style="font-family: Arial, sans-serif; text-align: center; padding: 15px;">' +
    '<p style="color: #2e7d32; font-weight: bold; font-size: 16px;">Payment export is ready.</p>' +
    '<a href="data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' + base64Data + '" ' +
    'download="' + fileName + '" style="background-color: #1f7246; color: white; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 5px;">' +
    'Download Excel (.xlsx)</a></div>'
  ).setWidth(450).setHeight(180);

  SpreadsheetApp.getUi().showModalDialog(htmlOutput, 'Export Payment Data');
}

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('LOAN EXPORT')
    .addItem('1. Process Payment To Template', 'processBongPrakToPayment')
    .addItem('2. Download Payment As Excel', 'exportPaymentSheetToExcel')
    .addToUi();
}

function findSourceSheet_(ss) {
  for (var i = 0; i < SOURCE_SHEET_CANDIDATES.length; i++) {
    var sheet = ss.getSheetByName(SOURCE_SHEET_CANDIDATES[i]);
    if (sheet) return sheet;
  }
  return null;
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

function pushPaymentRow_(outRows, seenKeys, loanNumber, paymentType, amount, paidDate, paymentMethod, referenceNumber, note) {
  var uniqueKey = [
    referenceNumber || '',
    loanNumber,
    paymentType,
    paidDate,
    round2_(amount),
    paymentMethod
  ].join('|');

  if (seenKeys[uniqueKey]) {
    return false;
  }

  seenKeys[uniqueKey] = true;
  outRows.push([
    loanNumber,
    '',
    '',
    paymentType,
    round2_(amount),
    paidDate,
    paymentMethod,
    DEFAULT_CURRENCY,
    DEFAULT_EXCHANGE_RATE,
    referenceNumber,
    note
  ]);

  return true;
}

function resolvePaymentType_(row) {
  if (isTruthy_(row[COL.PAY_OFF - 1])) {
    return 'loan';
  }
  return 'monthly';
}

function buildReference_(loanNumber, paidDate, monthNo, sourceRowNo, kind, sourceRef) {
  var safeMonthNo = normalizeMonthToken_(monthNo);
  var safeSourceRef = cleanRef_(sourceRef);
  var parts = [
    'IMP',
    loanNumber,
    paidDate,
    'M' + (safeMonthNo || '0'),
    'R' + sourceRowNo,
    kind
  ];

  if (safeSourceRef) {
    parts.push(safeSourceRef);
  }

  return parts.join('-');
}

function buildNote_(row, sourceRowNo, monthNo) {
  var parts = [];

  pushNotePart_(parts, 'source_row', sourceRowNo);
  pushNotePart_(parts, 'source_invoice', trim_(row[COL.INVOICE - 1]));
  pushNotePart_(parts, 'customer', trim_(row[COL.CUST_NAME - 1]));
  pushNotePart_(parts, 'phone', trim_(row[COL.PHONE - 1]));
  pushNotePart_(parts, 'months_to_pay', trim_(row[COL.MONTHS_TO_PAY - 1]));
  pushNotePart_(parts, 'month_no', monthNo);
  pushNotePart_(parts, 'total', numberText_(row[COL.TOTAL - 1]));
  pushNotePart_(parts, 'principal', numberText_(row[COL.PRINCIPAL - 1]));
  pushNotePart_(parts, 'interest', numberText_(row[COL.INTEREST - 1]));
  pushNotePart_(parts, 'penalty', numberText_(row[COL.PENALTY - 1]));
  pushNotePart_(parts, 'misc', numberText_(row[COL.MISC - 1]));
  pushNotePart_(parts, 'staff', trim_(row[COL.STAFF - 1]));
  pushNotePart_(parts, 'email', trim_(row[COL.EMAIL - 1]));
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

function autoResizeColumns_(sheet, totalColumns) {
  for (var c = 1; c <= totalColumns; c++) {
    sheet.autoResizeColumn(c);
  }
}

function normalizeLoanNumber_(value) {
  var text = trim_(value).replace(/\.0+$/, '');
  if (text === '') return '';

  var upperText = text.toUpperCase();
  if (upperText.indexOf(LOAN_PREFIX) === 0) {
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

function padLeft_(value, width) {
  var text = String(value);
  while (text.length < width) {
    text = '0' + text;
  }
  return text;
}

function formatDate_(value) {
  var d = value instanceof Date ? value : new Date(value);
  if (isNaN(d.getTime())) return null;
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

function isTruthy_(value) {
  var normalized = trim_(value).toLowerCase();
  return normalized === '1' ||
    normalized === 'true' ||
    normalized === 'yes' ||
    normalized === 'y' ||
    normalized === 'paid off' ||
    normalized === 'payoff' ||
    normalized === 'full' ||
    normalized === 'បង់ផ្តាច់' ||
    normalized === 'បង់ផ្ដាច់';
}

function formatToday_() {
  return Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd');
}
