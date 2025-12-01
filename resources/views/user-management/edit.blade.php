@extends('panel.layout.settings')
@section('title', __('Edit') . ' ' . $user?->fullName())
@section('titlebar_actions', '')

@section('settings')
    <form action="{{ route('dashboard.admin.hr.users.save') }}" method="POST">
        <div class="space-y-7">
            <div class="grid grid-cols-2 gap-x-4 gap-y-5">
                <input type="text" hidden name="user_id" value="{{ $user->id }}" />

                <x-forms.input-
                    id="name"
                    type="text"
                    name="name"
                    size="lg"
                    label="{{ __('Name') }}"
                    value="{{ $user->name }}"
                />

                <x-forms.input
                    id="surname"
                    type="text"
                    name="surname"
                    size="lg"
                    label="{{ __('Surname') }}"
                    value="{{ $user->surname }}"
                />

                <x-forms.input
                    id="phone"
                    data-mask="+0000000000000"
                    type="text"
                    name="phone"
                    size="lg"
                    placeholder="+000000000000"
                    label="{{ __('Phone') }}"
                    value="{{ $user->phone }}"
                />

                <x-forms.input
                    id="email"
                    type="email"
                    name="email"
                    size="lg"
                    label="{{ __('Email') }}"
                    value="{{ $user->email }}"
                />

                <x-forms.input
                    id="country"
                    container-class="w-full col-span-2"
                    type="select"
                    name="country"
                    size="lg"
                    label="{{ __('Country') }}"
                >
                    @include('panel.admin.users.countries')
                </x-forms.input>

                <x-forms.input
                    class:container="h-full bg-input-background border p-3 rounded"
                    type="select"
                    name="type[]"
                    label="{{ __('Role') }}"
                    multiple
                >
                    @foreach ($roles as $role)
                        <option
                            {{ in_array($role->name, $roleAssign) ? 'selected' : '' }}
                            value="{{ $role->name }}"
                        >
                            {{ $role->label }}
                        </option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="status"
                    type="select"
                    name="status"
                    size="lg"
                    label="{{ __('Status') }}"
                >
                    <option
                        value="1"
                        {{ $user->status == 1 ? 'selected' : '' }}
                    >
                        {{ __('Active') }}
                    </option>
                    <option
                        value="0"
                        {{ $user->status == 0 ? 'selected' : '' }}
                    >
                        {{ __('Passive') }}
                    </option>
                </x-forms.input>

                <x-forms.input
                    class:container="h-full bg-input-background border p-3 rounded"
                    type="select"
                    name="users[]"
                    label="{{ __('Assign to user') }}"
                    multiple
                    data-tomServerSide="true"
                    data-tomEndPoint="/api/users"
                    data-tomValueField="id"
                    data-tomLabelField="name"
                    data-tomSearchField="name"
                >
                        @if ($userAssigned)
                            @foreach ($selectedUsers as $userAssign)
                            <option
                                    selected
                                    value="{{ $userAssign['id'] }}"
                                >
                                    {{ $userAssign['name'] }}
                                </option>
                            @endforeach
                        @else

                        @endif 

                        <option
                            value="0"
                        >
                            Type for result
                        </option>
                </x-forms.input>

                <x-forms.input
                    class:container="h-full bg-input-background border p-3 rounded"
                    type="select"
                    name="as_a"
                    label="{{ __('Assign as a') }}"
                >
                    @foreach ($roles as $role)
                        <option
                            {{$role->name == $selectedAssgnAS_a ? 'selected' : ''}}
                            value="{{ $role->name }}"
                        >
                            {{ $role->label }}
                        </option>
                    @endforeach
                </x-forms.input>
            </div>

            <div class="mb-3">
                <div class="mb-3">
                    <div class="form-label">{{ __('Plans') }}</div>
                    <select
                        class="form-select"
                        id="status"
                        name="plan_id"
                    >
                        @foreach ($plans as $item)
                            <option value="{{$item->id}}" {{ $item->id === $user->plan_id ? 'selected' : '' }} >{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- <button type="button" id="add-user-role" class="mt-3 px-4 py-2 bg-blue-500 text-white rounded">
                + Add User Role
            </button> --}}
            

            <x-button
                class="w-full"
                id="user_edit_button"
                type="submit"
                size="lg"
            >
                {{ __('Save') }}
            </x-button>
        </div>
    </form>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/js/panel/user.js') }}"></script>
@endpush
