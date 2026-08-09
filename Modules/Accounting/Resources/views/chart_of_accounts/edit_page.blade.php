@extends('layouts.app')

@section('title', __('accounting::lang.edit_account'))

@section('content')

@include('accounting::layouts.nav')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('accounting::lang.edit_account')</h1>
</section>

<section class="content" id="edit_account_page">
    @include('accounting::chart_of_accounts.edit')
</section>

@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('#edit_account_page').find('#account_sub_type, #detail_type, #parent_account').select2({
            width: '100%'
        });

        init_tinymce('description');
    });

    $(document).on('change', '#account_primary_type', function() {
        if ($(this).val() !== '') {
            $.ajax({
                url: '/accounting/get-account-sub-types?account_primary_type=' + $(this).val(),
                dataType: 'json',
                success: function(result) {
                    $('#account_sub_type').select2('destroy')
                        .empty()
                        .select2({
                            data: result.sub_types,
                            width: '100%'
                        });
                    $('#account_sub_type').change();
                },
            });
        }
    });

    $(document).on('change', '#account_sub_type', function() {
        if ($(this).val() !== '') {
            $.ajax({
                url: '/accounting/get-account-details-types?account_type_id=' + $(this).val(),
                dataType: 'json',
                success: function(result) {
                    $('#detail_type').select2('destroy')
                        .empty()
                        .select2({
                            data: result.detail_types,
                            width: '100%'
                        }).on('change', function() {
                            if ($(this).val() !== '') {
                                var desc = $(this).select2('data')[0].description;
                                $('#detail_type_desc').html(desc);
                            }
                        });

                    $('#parent_account').select2('destroy')
                        .empty()
                        .select2({
                            data: result.parent_accounts,
                            width: '100%'
                        });
                },
            });
        }
    });
</script>
@endsection
