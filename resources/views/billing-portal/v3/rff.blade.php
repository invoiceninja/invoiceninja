<div>
    @if($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="handleRff">
        @csrf
    
        @if(strlen(auth()->guard('contact')->user()->first_name) === 0)
        <div class="col-auto mt-3">
            <label for="first_name" class="input-label">{{ ctrans('texts.first_name') }}</label>
            <input id="first_name" class="input w-full" wire:model="contact_first_name" />
        </div>
        @endif
    
        @if(strlen(auth()->guard('contact')->user()->last_name) === 0)
        <div class="col-auto mt-3 @if(auth()->guard('contact')->user()->last_name) !== 0) hidden @endif">
            <label for="last_name" class="input-label">{{ ctrans('texts.last_name') }}</label>
            <input id="last_name" class="input w-full" wire:model="contact_last_name" />
        </div>
        @endif
    
        @if(strlen(auth()->guard('contact')->user()->email) === 0)
        <div class="col-auto mt-3 @if(auth()->guard('contact')->user()->email) !== 0) hidden @endif">
            <label for="email" class="input-label">{{ ctrans('texts.email') }}</label>
            <input id="email" class="input w-full" wire:model="contact_email" />
        </div>
        @endif
    
        <button 
            type="submit"
            class="button button-block bg-primary text-white mt-4">
            {{ ctrans('texts.next') }}
        </button>
    </form>
</div>