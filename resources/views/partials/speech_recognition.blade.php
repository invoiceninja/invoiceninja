<i id="microphone" class="fa fa-microphone form-control-feedback"
  title="Say &quot;new invoice for [client]&quot; or &quot;show me [client]'s archived payments&quot;"
  data-toggle="tooltip" data-placement="bottom"
  onclick="onMicrophoneClick()" aria-hidden="true"></i>

<style type="text/css">
    #microphone {
        font-size: 16px;
        padding-top: 8px;
        cursor: pointer;
        pointer-events: auto;
        color: #888;
    }

    #microphone:hover {
        color: black;
    }
</style>

<script type="text/javascript">

    // https://developers.google.com/web/updates/2013/01/Voice-Driven-Web-Apps-Introduction-to-the-Web-Speech-API

    /*
    var langs =
    [['Afrikaans',       ['af-ZA']],
     ['Bahasa Indonesia',['id-ID']],
     ['Bahasa Melayu',   ['ms-MY']],
     ['Català',          ['ca-ES']],
     ['Čeština',         ['cs-CZ']],
     ['Dansk',           ['da-DK']],
     ['Deutsch',         ['de-DE']],
     ['English',         ['en-AU', 'Australia'],
                         ['en-CA', 'Canada'],
                         ['en-IN', 'India'],
                         ['en-NZ', 'New Zealand'],
                         ['en-ZA', 'South Africa'],
                         ['en-GB', 'United Kingdom'],
                         ['en-US', 'United States']],
     ['Español',         ['es-AR', 'Argentina'],
                         ['es-BO', 'Bolivia'],
                         ['es-CL', 'Chile'],
                         ['es-CO', 'Colombia'],
                         ['es-CR', 'Costa Rica'],
                         ['es-EC', 'Ecuador'],
                         ['es-SV', 'El Salvador'],
                         ['es-ES', 'España'],
                         ['es-US', 'Estados Unidos'],
                         ['es-GT', 'Guatemala'],
                         ['es-HN', 'Honduras'],
                         ['es-MX', 'México'],
                         ['es-NI', 'Nicaragua'],
                         ['es-PA', 'Panamá'],
                         ['es-PY', 'Paraguay'],
                         ['es-PE', 'Perú'],
                         ['es-PR', 'Puerto Rico'],
                         ['es-DO', 'República Dominicana'],
                         ['es-UY', 'Uruguay'],
                         ['es-VE', 'Venezuela']],
     ['Euskara',         ['eu-ES']],
     ['Filipino',        ['fil-PH']],
     ['Français',        ['fr-FR']],
     ['Galego',          ['gl-ES']],
     ['Hrvatski',        ['hr_HR']],
     ['IsiZulu',         ['zu-ZA']],
     ['Íslenska',        ['is-IS']],
     ['Italiano',        ['it-IT', 'Italia'],
                         ['it-CH', 'Svizzera']],
     ['Lietuvių',        ['lt-LT']],
     ['Magyar',          ['hu-HU']],
     ['Nederlands',      ['nl-NL']],
     ['Norsk bokmål',    ['nb-NO']],
     ['Polski',          ['pl-PL']],
     ['Português',       ['pt-BR', 'Brasil'],
                         ['pt-PT', 'Portugal']],
     ['Română',          ['ro-RO']],
     ['Slovenščina',     ['sl-SI']],
     ['Slovenčina',      ['sk-SK']],
     ['Suomi',           ['fi-FI']],
     ['Svenska',         ['sv-SE']],
     ['Tiếng Việt',      ['vi-VN']],
     ['Türkçe',          ['tr-TR']],
     ['Ελληνικά',        ['el-GR']],
     ['български',       ['bg-BG']],
     ['Pусский',         ['ru-RU']],
     ['Српски',          ['sr-RS']],
     ['Українська',      ['uk-UA']],
     ['한국어',            ['ko-KR']],
     ['中文',             ['cmn-Hans-CN', '普通话 (中国大陆)'],
                         ['cmn-Hans-HK', '普通话 (香港)'],
                         ['cmn-Hant-TW', '中文 (台灣)'],
                         ['yue-Hant-HK', '粵語 (香港)']],
     ['日本語',           ['ja-JP']],
     ['हिन्दी',            ['hi-IN']],
     ['ภาษาไทย',         ['th-TH']]];
     */

    var final_transcript = '';
    var recognizing = false;
    var ignore_onend;

    if (!('webkitSpeechRecognition' in window)) {
      $('.fa-microphone').hide();
    } else {
      var recognition = new webkitSpeechRecognition();
      recognition.continuous = false;
      recognition.interimResults = true;

      recognition.onstart = function() {
        recognizing = true;
      };

      recognition.onerror = function(event) {
        $('.fa-microphone').show();
        $('#search').val('');
        if (event.error == 'no-speech') {
          ignore_onend = true;
        }
        if (event.error == 'audio-capture') {
          ignore_onend = true;
        }
        if (event.error == 'not-allowed') {
          ignore_onend = true;
        }
      };

      recognition.onend = function() {
        recognizing = false;
        $('.fa-microphone').show();
        if (ignore_onend || !final_transcript) {
          $('#search').val('');
          return;
        }
        $('#search-form').submit();
      };

      recognition.onresult = function(event) {
        var interim_transcript = '';
        if (typeof(event.results) == 'undefined') {
          recognition.onend = null;
          recognition.stop();
          $('.fa-microphone').hide();
          $('#search').val('');
          return;
        }
        for (var i = event.resultIndex; i < event.results.length; ++i) {
          if (event.results[i].isFinal) {
            final_transcript += event.results[i][0].transcript;
          } else {
            interim_transcript += event.results[i][0].transcript;
          }
        }
        final_transcript = capitalize(final_transcript);
        var value = final_transcript || interim_transcript;
        var $search = document.getElementById('search');
        $search.value = value;
        $search.scrollLeft = $search.scrollWidth;
      };
    }

    var first_char = /\S/;
    function capitalize(s) {
      return s.replace(first_char, function(m) { return m.toUpperCase(); });
    }

    function onMicrophoneClick() {
      //$('#search').val("find david");
      //$('#search-form').submit();
      //return;

      $('.fa-microphone').hide();
      $('#search').val("{{ trans('texts.listening') }}");
      if (recognizing) {
        recognition.stop();
        return;
      }
      final_transcript = '';
      recognition.lang = 'en-US';
      recognition.start();
      ignore_onend = false;
    }

</script>
