<div class="row">
    <div class="col-md-6"><div class="form-group"><label>Workplace</label><input class="form-control" name="workplace" value="{{ old('workplace', $customerRow->workplace ?? '') }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Monthly Income</label><input class="form-control" name="monthly_income" value="{{ old('monthly_income', $customerRow->monthly_income ?? '') }}"></div></div>
    <div class="col-md-12"><div class="form-group"><label>Note</label><textarea class="form-control" name="note">{{ old('note', $customerRow->note ?? '') }}</textarea></div></div>
</div>
