<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\Rapports;
use App\Support\Logo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/** Export des rapports en PDF, Excel (XLSX) et CSV (§19). */
class RapportExportController extends Controller
{
    public function __invoke(Request $request, Rapports $rapports)
    {
        $format = $request->query('format', 'pdf');
        abort_unless(in_array($format, ['pdf', 'xlsx', 'csv'], true), 404);

        $type = (string) $request->query('type');
        $params = $request->only(['periode', 'annee', 'du', 'au', 'membre_id']);
        $rapport = $rapports->generer($type, $params);

        Audit::log('rapport.exporter', 'Rapport', null, ['type' => $type, 'format' => $format] + $params, $rapport['titre']);

        $fichier = Str::slug($rapport['titre']).'-'.now()->format('Ymd-His');

        return match ($format) {
            'pdf' => Pdf::loadView('pdf.rapport', ['r' => $rapport, 'logo' => Logo::dataUri()])
                ->setPaper('a4', count($rapport['colonnes']) > 6 ? 'landscape' : 'portrait')
                ->download($fichier.'.pdf'),
            'xlsx' => $this->xlsx($rapport, $fichier.'.xlsx'),
            'csv' => $this->csv($rapport, $fichier.'.csv'),
        };
    }

    private function valeurs(array $rapport, array $ligne, bool $brut): array
    {
        return array_map(function ($col) use ($ligne, $brut) {
            $v = $ligne[$col['cle']] ?? null;
            if ($brut && in_array($col['type'], ['montant', 'nombre'], true) && is_numeric($v)) {
                return (int) $v; // cellule numérique exploitable dans Excel
            }

            return Rapports::texte($v, $col['type']);
        }, $rapport['colonnes']);
    }

    private function xlsx(array $rapport, string $nom)
    {
        $chemin = tempnam(sys_get_temp_dir(), 'rap');
        $writer = new XlsxWriter;
        $writer->openToFile($chemin);
        $gras = (new Style)->withFontBold(true);

        $writer->addRow(Row::fromValuesWithStyle([$rapport['cooperative']], $gras));
        $writer->addRow(Row::fromValuesWithStyle([$rapport['titre']], $gras));
        $writer->addRow(Row::fromValues([$rapport['sous_titre'].' — généré le '.$rapport['genere_le']->format('d/m/Y H:i')]));
        foreach ($rapport['resume'] as $r) {
            $writer->addRow(Row::fromValues([$r['label'], $r['valeur']]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(array_column($rapport['colonnes'], 'label'), $gras));
        foreach ($rapport['lignes'] as $ligne) {
            $writer->addRow(Row::fromValues($this->valeurs($rapport, $ligne, true)));
        }
        if ($rapport['totaux']) {
            $writer->addRow(Row::fromValuesWithStyle($this->valeurs($rapport, $rapport['totaux'], true), $gras));
        }
        $writer->close();

        return response()->download($chemin, $nom, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function csv(array $rapport, string $nom)
    {
        return response()->streamDownload(function () use ($rapport) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents corrects à l'ouverture dans Excel
            fputcsv($out, [$rapport['titre'].' — '.$rapport['sous_titre']], ';', '"', '');
            fputcsv($out, array_column($rapport['colonnes'], 'label'), ';', '"', '');
            foreach ($rapport['lignes'] as $ligne) {
                fputcsv($out, $this->valeurs($rapport, $ligne, true), ';', '"', '');
            }
            if ($rapport['totaux']) {
                fputcsv($out, $this->valeurs($rapport, $rapport['totaux'], true), ';', '"', '');
            }
            fclose($out);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
