<?php

namespace Modules\WarrantyCardPrint\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [[
            'name' => 'warrantycardprint_module',
            'label' => 'Warranty Card Print Module',
            'default' => false,
        ]];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value' => 'warranty_card_print.view',
                'label' => 'Warranty Card Print (view)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        // Header entry is injected via get_additional_script() to keep core views untouched.
    }

    public function get_additional_script(): array
    {
        if (! auth()->check()) {
            return [];
        }

        $this->ensurePermissionsExist();

        if (! $this->userCanAny(['warranty_card_print.view', 'product.view', 'product.create'])) {
            return [];
        }

        $url = route('warranty-card-print.create');
        $modalUrl = route('warranty-card-print.create', ['modal' => 1]);
        $active = request()->segment(1) === 'warranty-card-print' ? ' tw-ring-2 tw-ring-white/70' : '';
        $adminButton = '<a href="'.$url.'" id="warranty_card_print_header_link" class="sm:tw-inline-flex tw-transition-all tw-duration-200 tw-gap-2 tw-bg-'.e(! empty(session('business.theme_color')) ? session('business.theme_color') : 'primary').'-800 hover:tw-bg-'.e(! empty(session('business.theme_color')) ? session('business.theme_color') : 'primary').'-700 tw-py-1.5 tw-px-3 tw-rounded-lg tw-items-center tw-justify-center tw-text-sm tw-font-medium tw-ring-1 tw-ring-white/10 hover:tw-text-white tw-text-white'.$active.'"><i class="fa fa-id-card tw-hidden md:tw-block"></i><span>Print Warranty Card</span></a>';
        $posButton = '<a href="#" data-href="'.$modalUrl.'" data-container=".view_modal" id="warranty_card_print_pos_header_link" title="Print Warranty Card" class="btn-modal tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-bg-white hover:tw-bg-white/60 tw-cursor-pointer tw-border-2 tw-w-auto tw-h-auto tw-py-1 tw-px-4 tw-rounded-md pull-right"><strong><i class="fa fa-id-card tw-text-[#00935F] !tw-text-sm"></i> &nbsp;Print Warranty Card</strong></a>';
        $additionalCss = <<<'CSS'
<style>
@font-face {
    font-family: 'NewTimes';
    src: url('/fonts/english/NewTimes.ttf') format('truetype');
}
@font-face {
    font-family: 'Khmer OS Battambang';
    src: url('/fonts/khmer/Battambang-Regular.ttf') format('truetype');
}
.warranty-card-preview-wrap {
    overflow: auto;
}
.warranty-card-modal .modal-body {
    max-height: calc(100vh - 180px);
    overflow-y: auto;
}
.manual-warranty-card {
    position: relative;
    width: 85.6mm;
    height: 53.98mm;
    font-family: Arial, sans-serif;
    box-sizing: border-box;
    background: #fff;
    color: #000;
    page-break-after: always;
    break-after: page;
}
.manual-warranty-card:last-child {
    page-break-after: auto;
    break-after: auto;
}
.manual-row-1,
.manual-row-1b {
    position: absolute;
    width: 25mm;
    height: 5mm;
    line-height: 5mm;
    white-space: nowrap;
}
.manual-row-2 {
    position: absolute;
    width: 62mm;
    height: 5mm;
    line-height: 5mm;
    white-space: nowrap;
}
.manual-row-3 {
    position: absolute;
    width: 66mm;
    height: 5mm;
    line-height: 5mm;
    white-space: nowrap;
}
.manual-row-4 {
    position: absolute;
    width: 20mm;
    height: 5mm;
    line-height: 5mm;
    white-space: nowrap;
}
.manual-field-1 {
    left: 14mm;
    top: 8.8mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 2mm;
}
.manual-field-2 {
    right: 3mm;
    top: 8.8mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 2mm;
}
.manual-field-3 {
    right: 3mm;
    top: 15.5mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 2mm;
}
.manual-field-4 {
    right: 3mm;
    top: 22mm;
    font-family: 'Khmer OS Battambang', serif;
    font-weight: bold;
    font-size: 9px;
    padding-left: 2mm;
}
.manual-field-5 {
    right: 3mm;
    top: 28mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 0.7mm;
}
.manual-field-6 {
    right: 3mm;
    top: 33.5mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 0.7mm;
}
</style>
CSS;

        return [
            'additional_css' => $additionalCss,
            'additional_js' => '<script>
                $(function () {
                    function formatWarrantyDate(value) {
                        if (!value) {
                            return "";
                        }
                        var parts = value.split("-");
                        if (parts.length !== 3) {
                            return value;
                        }
                        return parts[2] + " / " + parts[1] + " / " + parts[0];
                    }

                    function updateWarrantyPreview($root) {
                        $root.find(".js-card-input").each(function () {
                            $root.find("#" + $(this).data("target")).text($(this).val());
                        });
                        $root.find(".preview-start-date").text(formatWarrantyDate($root.find(".manual-start-date").val()));
                        $root.find(".preview-end-date").text(formatWarrantyDate($root.find(".manual-end-date").val()));
                    }

                    function buildWarrantyPrint($root) {
                        var copies = parseInt($root.find(".manual-copies").val(), 10);
                        copies = isNaN(copies) || copies < 1 ? 1 : copies;
                        copies = copies > 50 ? 50 : copies;

                        var cardHtml = $root.find(".warranty-card-preview").prop("outerHTML");
                        var html = "";
                        for (var i = 0; i < copies; i++) {
                            html += cardHtml;
                        }
                        $("#receipt_section").html(html).attr("data-warranty-card-print", "1");
                    }

                    function installWarrantyPrintPageStyle() {
                        $("#warranty_card_print_page_style").remove();
                        $("head").append("<style id=\"warranty_card_print_page_style\">@page{size:85.6mm 53.98mm;margin:0;}@media print{body.warranty-card-printing{margin:0;}}</style>");
                        $("body").addClass("warranty-card-printing");
                    }

                    function cleanupWarrantyPrint() {
                        $("#warranty_card_print_page_style").remove();
                        $("body").removeClass("warranty-card-printing");
                        if ($("#receipt_section").attr("data-warranty-card-print") === "1") {
                            $("#receipt_section").removeAttr("data-warranty-card-print").empty();
                        }
                    }

                    if ($("#pos_header_more_options").length && !$("#warranty_card_print_pos_header_link").length) {
                        $("#pos_header_more_options").prepend('.json_encode($posButton).');
                    }

                    if (!$("#pos_header_more_options").length && !$("#warranty_card_print_header_link").length) {
                        var buttonHtml = '.json_encode($adminButton).';
                        var $headerActions = $(".tw-flex.tw-flex-wrap.tw-items-center.tw-justify-end.tw-gap-3").first();
                        if (!$headerActions.length) {
                            $headerActions = $("details.tw-dw-dropdown").first().closest(".tw-flex");
                        }
                        if ($headerActions.length) {
                            $headerActions.prepend(buttonHtml);
                        }
                    }

                    $(document).on("shown.bs.modal", ".view_modal", function () {
                        var $workspace = $(this).find(".warranty-card-workspace");
                        if ($workspace.length) {
                            updateWarrantyPreview($workspace);
                        }
                    });

                    $(document).on("input change", ".warranty-card-workspace .js-card-input, .warranty-card-workspace .manual-start-date, .warranty-card-workspace .manual-end-date", function () {
                        updateWarrantyPreview($(this).closest(".warranty-card-workspace"));
                    });

                    $(document).on("click", ".print-warranty-card", function () {
                        var $workspace = $(this).closest(".modal-content").find(".warranty-card-workspace");
                        updateWarrantyPreview($workspace);
                        buildWarrantyPrint($workspace);
                        installWarrantyPrintPageStyle();
                        $(".view_modal").modal("hide");
                        setTimeout(function () {
                            window.print();
                        }, 200);
                    });

                    window.addEventListener("afterprint", cleanupWarrantyPrint);
                });
            </script>',
        ];
    }

    private function ensurePermissionsExist(): void
    {
        try {
            if (! Schema::hasTable('permissions')) {
                return;
            }

            Permission::firstOrCreate([
                'name' => 'warranty_card_print.view',
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }

    private function userCanAny(array $permissions): bool
    {
        $user = auth()->user();

        foreach ($permissions as $permission) {
            try {
                if ($user->can($permission)) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        return false;
    }
}
