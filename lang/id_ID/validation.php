<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'The :attribute harus diterima.', // The :attribute must be accepted
    'accepted_if' => 'The :attribute harus diterima ketika :other adalah :value.', // The :attribute must be accepted when :other is :value
    'active_url' => 'The :attribute bukan URL yang valid.', // The :attribute is not a valid URL
    'after' => 'The :attribute harus berupa tanggal setelah :date.', // The :attribute must be a date after :date
    'after_or_equal' => 'The :attribute harus berupa tanggal setelah atau sama dengan :date.', // The :attribute must be a date after or equal to :date
    'alpha' => 'The :attribute hanya boleh berisi huruf.', // The :attribute must only contain letters
    'alpha_dash' => 'The :attribute hanya boleh berisi huruf, angka, tanda hubung dan garis bawah.', // The :attribute must only contain letters, numbers, dashes and underscores
    'alpha_num' => 'The :attribute hanya boleh berisi huruf dan angka.', // The :attribute must only contain letters and numbers
    'array' => 'The :attribute harus berupa array.', // The :attribute must be an array
    'before' => 'The :attribute harus berupa tanggal sebelum :date.', // The :attribute must be a date before :date
    'before_or_equal' => 'The :attribute harus berupa tanggal sebelum atau sama dengan :date.', // The :attribute must be a date before or equal to :date
    'between' => [
        'array' => 'The :attribute harus memiliki antara :min dan :max item.', // The :attribute must have between :min and :max items
        'file' => 'The :attribute harus antara :min dan :max kilobyte.', // The :attribute must be between :min and :max kilobytes
        'numeric' => 'The :attribute harus antara :min dan :max.', // The :attribute must be between :min and :max
        'string' => 'The :attribute harus antara :min dan :max karakter.', // The :attribute must be between :min and :max characters
    ],
    'boolean' => 'Bidang :attribute harus benar atau salah.', // The :attribute field must be true or false
    'confirmed' => 'Konfirmasi :attribute tidak cocok.', // The :attribute confirmation does not match
    'current_password' => 'Kata sandi salah.', // The password is incorrect
    'date' => 'The :attribute bukan tanggal yang valid.', // The :attribute is not a valid date
    'date_equals' => 'The :attribute harus berupa tanggal yang sama dengan :date.', // The :attribute must be a date equal to :date
    'date_format' => 'The :attribute tidak cocok dengan format :format.', // The :attribute does not match the format :format
    'declined' => 'The :attribute harus ditolak.', // The :attribute must be declined
    'declined_if' => 'The :attribute harus ditolak ketika :other adalah :value.', // The :attribute must be declined when :other is :value
    'different' => 'The :attribute dan :other harus berbeda.', // The :attribute and :other must be different
    'digits' => 'The :attribute harus :digits digit.', // The :attribute must be :digits digits
    'digits_between' => 'The :attribute harus antara :min dan :max digit.', // The :attribute must be between :min and :max digits
    'dimensions' => 'The :attribute memiliki dimensi gambar yang tidak valid.', // The :attribute has invalid image dimensions
    'distinct' => 'Bidang :attribute memiliki nilai duplikat.', // The :attribute field has a duplicate value
    'email' => 'The :attribute harus berupa alamat email yang valid.', // The :attribute must be a valid email address
    'ends_with' => 'The :attribute harus diakhiri dengan salah satu dari: :values.', // The :attribute must end with one of the following: :values
    'enum' => 'The :attribute yang dipilih tidak valid.', // The selected :attribute is invalid
    'exists' => 'The :attribute yang dipilih tidak valid.', // The selected :attribute is invalid
    'file' => 'The :attribute harus berupa file.', // The :attribute must be a file
    'filled' => 'Bidang :attribute harus memiliki nilai.', // The :attribute field must have a value
    'gt' => [
        'array' => 'The :attribute harus memiliki lebih dari :value item.', // The :attribute must have more than :value items
        'file' => 'The :attribute harus lebih besar dari :value kilobyte.', // The :attribute must be greater than :value kilobytes
        'numeric' => 'The :attribute harus lebih besar dari :value.', // The :attribute must be greater than :value
        'string' => 'The :attribute harus lebih besar dari :value karakter.', // The :attribute must be greater than :value characters
    ],
    'gte' => [
        'array' => 'The :attribute harus memiliki :value item atau lebih.', // The :attribute must have :value items or more
        'file' => 'The :attribute harus lebih besar dari atau sama dengan :value kilobyte.', // The :attribute must be greater than or equal to :value kilobytes
        'numeric' => 'The :attribute harus lebih besar dari atau sama dengan :value.', // The :attribute must be greater than or equal to :value
        'string' => 'The :attribute harus lebih besar dari atau sama dengan :value karakter.', // The :attribute must be greater than or equal to :value characters
    ],
    'image' => 'The :attribute harus berupa gambar.', // The :attribute must be an image
    'in' => 'The :attribute yang dipilih tidak valid.', // The selected :attribute is invalid
    'in_array' => 'Bidang :attribute tidak ada dalam :other.', // The :attribute field does not exist in :other
    'integer' => 'The :attribute harus berupa integer.', // The :attribute must be an integer
    'ip' => 'The :attribute harus berupa alamat IP yang valid.', // The :attribute must be a valid IP address
    'ipv4' => 'The :attribute harus berupa alamat IPv4 yang valid.', // The :attribute must be a valid IPv4 address
    'ipv6' => 'The :attribute harus berupa alamat IPv6 yang valid.', // The :attribute must be a valid IPv6 address
    'json' => 'The :attribute harus berupa string JSON yang valid.', // The :attribute must be a valid JSON string
    'lt' => [
        'array' => 'The :attribute harus memiliki kurang dari :value item.', // The :attribute must have less than :value items
        'file' => 'The :attribute harus kurang dari :value kilobyte.', // The :attribute must be less than :value kilobytes
        'numeric' => 'The :attribute harus kurang dari :value.', // The :attribute must be less than :value
        'string' => 'The :attribute harus kurang dari :value karakter.', // The :attribute must be less than :value characters
    ],
    'lte' => [
        'array' => 'The :attribute tidak boleh memiliki lebih dari :value item.', // The :attribute must not have more than :value items
        'file' => 'The :attribute harus kurang dari atau sama dengan :value kilobyte.', // The :attribute must be less than or equal to :value kilobytes
        'numeric' => 'The :attribute harus kurang dari atau sama dengan :value.', // The :attribute must be less than or equal to :value
        'string' => 'The :attribute harus kurang dari atau sama dengan :value karakter.', // The :attribute must be less than or equal to :value characters
    ],
    'mac_address' => 'The :attribute harus berupa alamat MAC yang valid.', // The :attribute must be a valid MAC address
    'max' => [
        'array' => 'The :attribute tidak boleh memiliki lebih dari :max item.', // The :attribute must not have more than :max items
        'file' => 'The :attribute tidak boleh lebih besar dari :max kilobyte.', // The :attribute must not be greater than :max kilobytes
        'numeric' => 'The :attribute tidak boleh lebih besar dari :max.', // The :attribute must not be greater than :max
        'string' => 'The :attribute tidak boleh lebih besar dari :max karakter.', // The :attribute must not be greater than :max characters
    ],
    'mimes' => 'The :attribute harus berupa file bertipe: :values.', // The :attribute must be a file of type: :values
    'mimetypes' => 'The :attribute harus berupa file bertipe: :values.', // The :attribute must be a file of type: :values
    'min' => [
        'array' => 'The :attribute harus memiliki minimal :min item.', // The :attribute must have at least :min items
        'file' => 'The :attribute harus minimal :min kilobyte.', // The :attribute must be at least :min kilobytes
        'numeric' => 'The :attribute harus minimal :min.', // The :attribute must be at least :min
        'string' => 'The :attribute harus minimal :min karakter.', // The :attribute must be at least :min characters
    ],
    'multiple_of' => 'The :attribute harus kelipatan dari :value.', // The :attribute must be a multiple of :value
    'not_in' => 'The :attribute yang dipilih tidak valid.', // The selected :attribute is invalid
    'not_regex' => 'Format :attribute tidak valid.', // The :attribute format is invalid
    'numeric' => 'The :attribute harus berupa angka.', // The :attribute must be a number
    'password' => [
        'letters' => 'The :attribute harus mengandung minimal satu huruf.', // The :attribute must contain at least one letter
        'mixed' => 'The :attribute harus mengandung minimal satu huruf besar dan satu huruf kecil.', // The :attribute must contain at least one uppercase and one lowercase letter
        'numbers' => 'The :attribute harus mengandung minimal satu angka.', // The :attribute must contain at least one number
        'symbols' => 'The :attribute harus mengandung minimal satu simbol.', // The :attribute must contain at least one symbol
        'uncompromised' => 'The :attribute yang diberikan telah muncul dalam kebocoran data. Silakan pilih :attribute yang berbeda.', // The given :attribute has appeared in a data leak. Please choose a different :attribute
    ],
    'present' => 'Bidang :attribute harus ada.', // The :attribute field must be present
    'prohibited' => 'Bidang :attribute dilarang.', // The :attribute field is prohibited
    'prohibited_if' => 'Bidang :attribute dilarang ketika :other adalah :value.', // The :attribute field is prohibited when :other is :value
    'prohibited_unless' => 'Bidang :attribute dilarang kecuali :other ada dalam :values.', // The :attribute field is prohibited unless :other is in :values
    'prohibits' => 'Bidang :attribute melarang :other untuk ada.', // The :attribute field prohibits :other from being present
    'regex' => 'Format :attribute tidak valid.', // The :attribute format is invalid
    'required' => 'Bidang :attribute wajib diisi.', // The :attribute field is required
    'required_array_keys' => 'Bidang :attribute harus berisi entri untuk: :values.', // The :attribute field must contain entries for: :values
    'required_if' => 'Bidang :attribute wajib diisi ketika :other adalah :value.', // The :attribute field is required when :other is :value
    'required_unless' => 'Bidang :attribute wajib diisi kecuali :other ada dalam :values.', // The :attribute field is required unless :other is in :values
    'required_with' => 'Bidang :attribute wajib diisi ketika :values ada.', // The :attribute field is required when :values is present
    'required_with_all' => 'Bidang :attribute wajib diisi ketika :values ada.', // The :attribute field is required when :values are present
    'required_without' => 'Bidang :attribute wajib diisi ketika :values tidak ada.', // The :attribute field is required when :values is not present
    'required_without_all' => 'Bidang :attribute wajib diisi ketika tidak ada :values yang ada.', // The :attribute field is required when none of :values are present
    'same' => 'The :attribute dan :other harus cocok.', // The :attribute and :other must match
    'size' => [
        'array' => 'The :attribute harus berisi :size item.', // The :attribute must contain :size items
        'file' => 'The :attribute harus :size kilobyte.', // The :attribute must be :size kilobytes
        'numeric' => 'The :attribute harus :size.', // The :attribute must be :size
        'string' => 'The :attribute harus :size karakter.', // The :attribute must be :size characters
    ],
    'starts_with' => 'The :attribute harus dimulai dengan salah satu dari: :values.', // The :attribute must start with one of the following: :values
    'doesnt_start_with' => 'The :attribute tidak boleh dimulai dengan salah satu dari: :values.', // The :attribute may not start with one of the following: :values
    'string' => 'The :attribute harus berupa string.', // The :attribute must be a string
    'timezone' => 'The :attribute harus berupa timezone yang valid.', // The :attribute must be a valid timezone
    'unique' => 'The :attribute sudah diambil.', // The :attribute has already been taken
    'uploaded' => 'The :attribute gagal diunggah.', // The :attribute failed to upload
    'url' => 'The :attribute harus berupa URL yang valid.', // The :attribute must be a valid URL
    'uuid' => 'The :attribute harus berupa UUID yang valid.', // The :attribute must be a valid UUID

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
