<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        @php
            $form_id = $quick_add ?? false ? 'quick_add_contact' : 'contact_add_form';
            $contact_type = $selected_type ?? 'customer';
        @endphp
        {!! Form::open(['url' => route('loan-management.customers.store'), 'method' => 'post', 'id' => $form_id]) !!}
        @csrf

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">Add Customer</h4>
        </div>

        <div class="modal-body">
            @if (! empty($types))
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('type', 'Type:*') !!}
                            {!! Form::select('type', $types, $contact_type, ['class' => 'form-control', 'id' => 'contact_type']); !!}
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('name', 'Name:*') !!}
                        {!! Form::text('name', null, ['class' => 'form-control', 'required']); !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('khmer_name', 'Khmer Name:') !!}
                        {!! Form::text('khmer_name', null, ['class' => 'form-control']); !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('phone', 'Phone:*') !!}
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                            {!! Form::text('phone', null, ['class' => 'form-control', 'required']); !!}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('email', 'Email:') !!}
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('address', 'Address:') !!}
                        <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
        {!! Form::close() !!}
    </div>
</div>