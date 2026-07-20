@if(session('user.language', config('app.locale')) === 'km')
<script>
(function() {
    var dictionary = {
        'Loan Management': 'គ្រប់គ្រងកម្ចី',
        'Dedicated loan operation workspace': 'កន្លែងធ្វើការសម្រាប់ប្រតិបត្តិការកម្ចី',
        'Dashboard': 'ផ្ទាំងគ្រប់គ្រង',
        'Admin Loan': 'រដ្ឋបាលកម្ចី',
        'Loan Operations': 'ប្រតិបត្តិការកម្ចី',
        'All Loans': 'កម្ចីទាំងអស់',
        'Create Loan': 'បង្កើតកម្ចី',
        'Create From POS': 'បង្កើតពី POS',
        'Loan Calculator': 'ម៉ាស៊ីនគណនាកម្ចី',
        'Calc': 'គណនា',
        'Due Today': 'ដល់កំណត់ថ្ងៃនេះ',
        'Partial Payments': 'បង់ប្រាក់មិនទាន់គ្រប់',
        'Closed Accounts': 'គណនីបានបិទ',
        'Collection Cases': 'ករណីប្រមូលប្រាក់',
        'Overdue Accounts': 'គណនីហួសកំណត់',
        'Promise To Pay': 'សន្យាបង់ប្រាក់',
        'Broken Promise': 'ខកខានសន្យា',
        'Field Visit Required': 'ត្រូវចុះទីតាំង',
        'Skip Customers': 'អតិថិជនបាត់ទំនាក់ទំនង',
        'Delinquent Accounts': 'គណនីយឺតយ៉ាវ',
        'Recovery Management': 'គ្រប់គ្រងការទាមទារ',
        'Debt Collection': 'ប្រមូលបំណុល',
        'Risk & Legal': 'ហានិភ័យ និងច្បាប់',
        'High Risk Customers': 'អតិថិជនហានិភ័យខ្ពស់',
        'Fraud Risk': 'ហានិភ័យបន្លំ',
        'Legal Cases': 'ករណីច្បាប់',
        'Blacklisted Customers': 'អតិថិជនបញ្ជីខ្មៅ',
        'Repossessions': 'ដកហូតទំនិញ',
        'Customer Management': 'គ្រប់គ្រងអតិថិជន',
        'Customers': 'អតិថិជន',
        'Clone From POS': 'ចម្លងពី POS',
        'Guarantors': 'អ្នកធានា',
        'Blacklist': 'បញ្ជីខ្មៅ',
        'Contact History': 'ប្រវត្តិទំនាក់ទំនង',
        'Collection Visits': 'ការចុះប្រមូលប្រាក់',
        'Communication': 'ទំនាក់ទំនង',
        'Live Chat': 'ជជែកផ្ទាល់',
        'Voice Calls': 'ការហៅទូរស័ព្ទ',
        'Notifications': 'ការជូនដំណឹង',
        'SMS/Telegram Logs': 'កំណត់ត្រា SMS/Telegram',
        'Finance': 'ហិរញ្ញវត្ថុ',
        'Payments': 'ការបង់ប្រាក់',
        'Payment History': 'ប្រវត្តិបង់ប្រាក់',
        'Customer Deposit Payments': 'ប្រាក់កក់អតិថិជន',
        'Interest / Collection Payments': 'ការបង់ការប្រាក់ / ប្រមូលប្រាក់',
        'ABA Transactions': 'ប្រតិបត្តិការ ABA',
        'Reports': 'របាយការណ៍',
        'Installment Reports': 'របាយការណ៍បង់រំលស់',
        'Collection Payment Reports': 'របាយការណ៍ប្រមូលប្រាក់',
        'Deposit Payment Reports': 'របាយការណ៍ប្រាក់កក់',
        'Collection Reports': 'របាយការណ៍ប្រមូលប្រាក់',
        'Tools': 'ឧបករណ៍',
        'Loan Import/Export': 'នាំចូល/នាំចេញកម្ចី',
        'Monthly Payments Import/Export': 'នាំចូល/នាំចេញការបង់ប្រចាំខែ',
        'Send Notification': 'ផ្ញើការជូនដំណឹង',
        'GPS Tracking': 'តាមដាន GPS',
        'Activity Logs': 'កំណត់ត្រាសកម្មភាព',
        'Settings': 'ការកំណត់',
        'Manage Users': 'គ្រប់គ្រងអ្នកប្រើ',
        'Invoice Prefix': 'បុព្វបទវិក្កយបត្រ',
        'Locations': 'ទីតាំង',
        'Payment Methods': 'វិធីបង់ប្រាក់',
        'Currencies': 'រូបិយប័ណ្ណ',
        'Soon': 'ឆាប់ៗនេះ',
        'New Loan': 'កម្ចីថ្មី',
        'From POS': 'ពី POS',
        'Overdue': 'ហួសកំណត់',
        'POS Sell': 'លក់ POS',
        'Chat': 'ជជែក',
        'Back to Main': 'ត្រឡប់ទៅមេ',
        'Logout': 'ចាកចេញ',
        'Loading form...': 'កំពុងផ្ទុកទម្រង់...',
        'Loading payment form...': 'កំពុងផ្ទុកទម្រង់បង់ប្រាក់...',
        'Home': 'ទំព័រដើម',
        'Actions': 'សកម្មភាព',
        'Action': 'សកម្មភាព',
        'Search': 'ស្វែងរក',
        'Filter': 'ចម្រោះ',
        'Apply': 'អនុវត្ត',
        'Reset': 'កំណត់ឡើងវិញ',
        'Clear': 'សម្អាត',
        'Cancel': 'បោះបង់',
        'Close': 'បិទ',
        'Save': 'រក្សាទុក',
        'Update': 'ធ្វើបច្ចុប្បន្នភាព',
        'Edit': 'កែប្រែ',
        'Delete': 'លុប',
        'View': 'មើល',
        'Pay': 'បង់ប្រាក់',
        'Print': 'បោះពុម្ព',
        'Copy': 'ចម្លង',
        'Copy as Image': 'ចម្លងជារូបភាព',
        'Download': 'ទាញយក',
        'Export': 'នាំចេញ',
        'Import': 'នាំចូល',
        'Template': 'គំរូ',
        'Add': 'បន្ថែម',
        'Add File': 'បន្ថែមឯកសារ',
        'Add another link': 'បន្ថែមតំណផ្សេងទៀត',
        'Remove link': 'លុបតំណ',
        'Documents': 'ឯកសារ',
        'Document': 'ឯកសារ',
        'Photos, PDFs, Text files': 'រូបថត PDF និងឯកសារអត្ថបទ',
        '(Photos, PDFs, Text files)': '(រូបថត PDF និងឯកសារអត្ថបទ)',
        'Customer Information': 'ព័ត៌មានអតិថិជន',
        'Search Existing Customer': 'ស្វែងរកអតិថិជនមានស្រាប់',
        'Clear Selected Customer': 'សម្អាតអតិថិជនដែលបានជ្រើស',
        'ID Card Photo': 'រូបថតអត្តសញ្ញាណប័ណ្ណ',
        'Take Photo': 'ថតរូប',
        'Upload': 'ផ្ទុកឡើង',
        'Name in Khmer': 'ឈ្មោះជាខ្មែរ',
        'Name in English': 'ឈ្មោះជាអង់គ្លេស',
        'Phone': 'ទូរស័ព្ទ',
        'Alternate Phone': 'លេខទូរស័ព្ទបន្ថែម',
        'ID Card Number': 'លេខអត្តសញ្ញាណប័ណ្ណ',
        'ID Card Address': 'អាសយដ្ឋានលើអត្តសញ្ញាណប័ណ្ណ',
        'Customer Group': 'ក្រុមអតិថិជន',
        'Group': 'ក្រុម',
        'Address': 'អាសយដ្ឋាន',
        'Province': 'ខេត្ត',
        'District': 'ស្រុក/ខណ្ឌ',
        'Commune': 'ឃុំ/សង្កាត់',
        'Village': 'ភូមិ',
        'Products': 'ទំនិញ',
        'Product': 'ទំនិញ',
        'Product name': 'ឈ្មោះទំនិញ',
        'Product Name': 'ឈ្មោះទំនិញ',
        'Model Name': 'ឈ្មោះម៉ូដែល',
        'Quantity': 'ចំនួន',
        'Qty': 'ចំនួន',
        'Price': 'តម្លៃ',
        'Unit Price': 'តម្លៃឯកតា',
        'Total': 'សរុប',
        'Color': 'ពណ៌',
        'Storage': 'ទំហំផ្ទុក',
        'Serial Number': 'លេខស៊េរី',
        'IMEI': 'IMEI',
        'Product Photo': 'រូបថតទំនិញ',
        'Product fields filled automatically.': 'ព័ត៌មានទំនិញបានបំពេញដោយស្វ័យប្រវត្តិ។',
        'OCR finished, but no matching fields were found.': 'OCR រួចរាល់ ប៉ុន្តែមិនឃើញព័ត៌មានដែលត្រូវគ្នា។',
        'Reading product photo...': 'កំពុងអានរូបថតទំនិញ...',
        'Reading ID card...': 'កំពុងអានអត្តសញ្ញាណប័ណ្ណ...',
        'ID card text extracted.': 'បានដកស្រង់អត្ថបទពីអត្តសញ្ញាណប័ណ្ណ។',
        'Product photo text extracted.': 'បានដកស្រង់អត្ថបទពីរូបថតទំនិញ។',
        'Preparing ID card photo...': 'កំពុងរៀបចំរូបថតអត្តសញ្ញាណប័ណ្ណ...',
        'Preparing cropped ID card photo...': 'កំពុងរៀបចំរូបថតអត្តសញ្ញាណប័ណ្ណដែលកាត់...',
        'Crop ID Card Photo': 'កាត់រូបថតអត្តសញ្ញាណប័ណ្ណ',
        'Reset': 'កំណត់ឡើងវិញ',
        'Use Original': 'ប្រើរូបដើម',
        'Use Original Photo': 'ប្រើរូបដើម',
        'Use Cropped Photo': 'ប្រើរូបដែលបានកាត់',
        'Loan Terms': 'លក្ខខណ្ឌកម្ចី',
        'Location': 'ទីតាំង',
        'Collector': 'អ្នកប្រមូលប្រាក់',
        'Loan Date': 'កាលបរិច្ឆេទកម្ចី',
        'First Due Date': 'ថ្ងៃដល់កំណត់លើកទី១',
        'Duration': 'រយៈពេល',
        'Duration Months': 'រយៈពេលខែ',
        'Installments': 'វគ្គបង់',
        'Interest Rate': 'អត្រាការប្រាក់',
        'Interest Type': 'ប្រភេទការប្រាក់',
        'Flat': 'ថេរ',
        'Reducing': 'ថយចុះ',
        'Down Payment': 'ប្រាក់កក់',
        'Principal': 'ប្រាក់ដើម',
        'Balance': 'សមតុល្យ',
        'Payment Information': 'ព័ត៌មានការបង់ប្រាក់',
        'Payment Method': 'វិធីបង់ប្រាក់',
        'Paid Amount': 'ចំនួនបានបង់',
        'Paid Date': 'ថ្ងៃបានបង់',
        'Reference Number': 'លេខយោង',
        'Schedule Preview': 'មើលកាលវិភាគបង់ប្រាក់',
        'Preview Schedule': 'មើលកាលវិភាគ',
        'Create Loan': 'បង្កើតកម្ចី',
        'Loan created successfully': 'បានបង្កើតកម្ចីដោយជោគជ័យ',
        'Recently Created Loans': 'កម្ចីដែលបានបង្កើតថ្មីៗ',
        'Latest loans for quick review after creating a new one.': 'កម្ចីចុងក្រោយសម្រាប់ពិនិត្យរហ័សបន្ទាប់ពីបង្កើតថ្មី។',
        'View All Loans': 'មើលកម្ចីទាំងអស់',
        'Loan': 'កម្ចី',
        'Customer': 'អតិថិជន',
        'Date': 'កាលបរិច្ឆេទ',
        'Status': 'ស្ថានភាព',
        'Draft': 'ព្រាង',
        'Active': 'សកម្ម',
        'Paid': 'បានបង់',
        'Unpaid': 'មិនទាន់បង់',
        'Partial': 'បង់ខ្លះ',
        'Approved': 'បានអនុម័ត',
        'Pending': 'កំពុងរង់ចាំ',
        'No loans created yet.': 'មិនទាន់មានកម្ចីត្រូវបានបង្កើត។',
        'Loan Locations': 'ទីតាំងកម្ចី',
        'Manage loan branches, invoice prefixes, print assets, and Telegram routing': 'គ្រប់គ្រងសាខាកម្ចី បុព្វបទវិក្កយបត្រ ទ្រព្យសម្បត្តិបោះពុម្ព និង Telegram',
        'All Loan Locations': 'ទីតាំងកម្ចីទាំងអស់',
        'Location ID': 'លេខសម្គាល់ទីតាំង',
        'Loan Invoice Prefix': 'បុព្វបទវិក្កយបត្រកម្ចី',
        'Assets': 'ទ្រព្យសម្បត្តិ',
        'Sync POS Locations': 'ធ្វើសមកាលកម្មទីតាំង POS',
        'No locations found.': 'រកមិនឃើញទីតាំង។',
        'Location added successfully.': 'បានបន្ថែមទីតាំងដោយជោគជ័យ។',
        'Location updated successfully.': 'បានធ្វើបច្ចុប្បន្នភាពទីតាំងដោយជោគជ័យ។',
        'Location deleted successfully.': 'បានលុបទីតាំងដោយជោគជ័យ។',
        'POS locations synced.': 'បានធ្វើសមកាលកម្មទីតាំង POS។',
        'Payment QR': 'QR បង់ប្រាក់',
        'Telegram QR': 'QR Telegram',
        'Telegram Test': 'សាកល្បង Telegram',
        'Test': 'សាកល្បង',
        'Invoice No': 'លេខវិក្កយបត្រ',
        'Invoice': 'វិក្កយបត្រ',
        'Customer Chat': 'ជជែកអតិថិជន',
        'Quick Actions': 'សកម្មភាពរហ័ស',
        'Collect Payment': 'ប្រមូលប្រាក់',
        'Print Invoice': 'បោះពុម្ពវិក្កយបត្រ',
        'Edit Loan': 'កែប្រែកម្ចី',
        'Loan Items': 'ទំនិញកម្ចី',
        'Add Item': 'បន្ថែមទំនិញ',
        'Edit Item': 'កែប្រែទំនិញ',
        'Delete Item': 'លុបទំនិញ',
        'Hide': 'លាក់',
        'Show': 'បង្ហាញ',
        'All': 'ទាំងអស់',
        'All Locations': 'ទីតាំងទាំងអស់',
        'Search loan #, customer name, phone...': 'ស្វែងរកលេខកម្ចី ឈ្មោះអតិថិជន លេខទូរស័ព្ទ...',
        'Search by name, phone, or loan # to collect payment.': 'ស្វែងរកតាមឈ្មោះ លេខទូរស័ព្ទ ឬលេខកម្ចីដើម្បីប្រមូលប្រាក់។',
        'Type to search for payment collection.': 'វាយដើម្បីស្វែងរកសម្រាប់ប្រមូលប្រាក់។',
        'Search customer name or phone for fast payment.': 'ស្វែងរកឈ្មោះអតិថិជន ឬលេខទូរស័ព្ទសម្រាប់បង់ប្រាក់រហ័ស។',
        'No loans found for this search.': 'រកមិនឃើញកម្ចីសម្រាប់ការស្វែងរកនេះ។',
        'Search failed.': 'ស្វែងរកបរាជ័យ។',
        'Name': 'ឈ្មោះ',
        'Email': 'អ៊ីមែល',
        'Username': 'ឈ្មោះអ្នកប្រើ',
        'Role': 'តួនាទី',
        'Language': 'ភាសា',
        'English': 'អង់គ្លេស',
        'Khmer': 'ខ្មែរ',
        'Write document note or extra information to send with Telegram': 'សរសេរកំណត់ចំណាំឯកសារ ឬព័ត៌មានបន្ថែមដើម្បីផ្ញើទៅ Telegram',
        'Paste document link': 'បិទភ្ជាប់តំណឯកសារ',
        'Type name or phone to search...': 'វាយឈ្មោះ ឬលេខទូរស័ព្ទដើម្បីស្វែងរក...',
        'Khmer name': 'ឈ្មោះខ្មែរ',
        'English name': 'ឈ្មោះអង់គ្លេស',
        'Phone number': 'លេខទូរស័ព្ទ',
        'Alternate phone': 'លេខទូរស័ព្ទបន្ថែម',
        'ID Card': 'អត្តសញ្ញាណប័ណ្ណ',
        'Product fields filled automatically.': 'ព័ត៌មានទំនិញបានបំពេញដោយស្វ័យប្រវត្តិ។',
        'Tip: You can paste images from clipboard (Ctrl+V / Cmd+V) anywhere on this page': 'គន្លឹះ៖ អ្នកអាចបិទភ្ជាប់រូបភាពពី Clipboard (Ctrl+V / Cmd+V) នៅលើទំព័រនេះ',
        'Paste images with Ctrl+V · Photos compressed, files kept as-is': 'បិទភ្ជាប់រូបភាពដោយ Ctrl+V · រូបថតត្រូវបានបង្រួម ឯកសារផ្សេងទុកដូចដើម'
    };

    var skipTags = { SCRIPT: true, STYLE: true, TEXTAREA: true, CODE: true, PRE: true };
    var attrs = ['placeholder', 'title', 'aria-label', 'data-title'];

    function normalized(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function translateText(value) {
        var key = normalized(value);
        return dictionary[key] || null;
    }

    function translateElementAttributes(element) {
        attrs.forEach(function(attr) {
            if (!element.hasAttribute || !element.hasAttribute(attr)) return;
            var translated = translateText(element.getAttribute(attr));
            if (translated) element.setAttribute(attr, translated);
        });
    }

    function translateTextNode(node) {
        var translated = translateText(node.nodeValue);
        if (!translated) return;
        var prefix = (node.nodeValue.match(/^\s*/) || [''])[0];
        var suffix = (node.nodeValue.match(/\s*$/) || [''])[0];
        node.nodeValue = prefix + translated + suffix;
    }

    function translate(root) {
        if (!root) return;
        if (root.nodeType === Node.ELEMENT_NODE) {
            if (skipTags[root.tagName]) return;
            translateElementAttributes(root);
        }

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
            acceptNode: function(node) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    return skipTags[node.tagName] ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
                }
                return normalized(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });

        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(function(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                translateTextNode(node);
            } else {
                translateElementAttributes(node);
            }
        });
    }

    function boot() {
        translate(document.body);
        new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    translate(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endif
