<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\Designs\Designer;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\View\Factory;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Throwable;

/**
 * Class MakesInvoiceHtml.
 */
trait MakesInvoiceHtml
{
    /**
     * Generate the HTML invoice parsing variables
     * and generating the final invoice HTML.
     *
     * @param $labels
     * @param $values
     * @param $section
     * @return string           The invoice string in HTML format
     * @deprecated replaced by generateEntityHtml
     *
     */
    // public function generateEntityHtml(Designer $designer, $entity, $contact = null) :string
    // {
    //     $entity->load('client');

    //     $client = $entity->client;

    //     App::setLocale($client->preferredLocale());

    //     $values_and_labels = $entity->buildLabelsAndValues($contact);

    //     $designer->build();

    //     $data = [];
    //     $data['entity'] = $entity;
    //     $data['lang'] = $client->preferredLocale();
    //     $data['includes'] = $designer->getIncludes();
    //     $data['header'] = $designer->getHeader();
    //     $data['body'] = $designer->getBody();
    //     $data['footer'] = $designer->getFooter();

    //     $html = view('pdf.stub', $data)->render();

    //     $html = $this->parseLabelsAndValues($values_and_labels['labels'], $values_and_labels['values'], $html);

    //     return $html;
    // }

    // public function generateEmailEntityHtml($entity, $content, $contact = null) :string
    // {
    //     $entity->load('client');

    //     $client = $entity->client;

    //     App::setLocale($client->preferredLocale());

    //     $data = $entity->buildLabelsAndValues($contact);

    //     return $this->parseLabelsAndValues($data['labels'], $data['values'], $content);
    // }

    private function parseLabelsAndValues($labels, $values, $section) :string
    {
        $section = strtr($section, $labels);
        $section = strtr($section, $values);

        return $section;
    }

    /**
     * Parses the blade file string and processes the template variables.
     *
     * @param string $string The Blade file string
     * @param array $data The array of template variables
     * @return string         The return HTML string
     * @throws FatalThrowableError
     */
    public function renderView($string, $data = []) :string
    {
        $data['__env'] = app(Factory::class);

        $php = Blade::compileString($string);

        $obLevel = ob_get_level();
        ob_start();
        extract($data, EXTR_SKIP);

        try {
            eval('?'.'>'.$php);
        } catch (Exception $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            throw $e;
        } catch (Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            throw new FatalThrowableError($e);
        }

        return ob_get_clean();
    }

    /*
     * Returns the base template we will be using.
     */
    public function getTemplate(string $template = 'plain')
    {
        return File::get(resource_path('views/email/template/'.$template.'.blade.php'));
    }

    public function getTemplatePath(string $template = 'plain')
    {
        return 'email.template.'.$template;
    }
}
