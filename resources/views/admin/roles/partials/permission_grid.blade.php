{{--
    The permission checkbox grid, shared by the create and edit role forms.

    Built straight from config/permissions.php ($modules), so adding an atom to
    that file makes it appear here with no template change. $current is an
    atom => index map of what's already ticked.
--}}
<div class="row g-3">
    @foreach($modules as $module)
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header py-2"><strong>{{ $module['label'] }}</strong></div>
                <div class="card-body py-2">
                    @foreach($module['atoms'] as $atom => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="atoms[]"
                                   value="{{ $atom }}" id="atom_{{ $atom }}"
                                   @checked(in_array($atom, old('atoms', array_keys($current)), true))>
                            <label class="form-check-label" for="atom_{{ $atom }}">
                                {{ $label }}
                                <code class="small text-body-secondary ms-1">{{ $atom }}</code>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
