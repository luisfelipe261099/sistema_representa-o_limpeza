<?php
/**
 * PDFHelper - Classe auxiliar para geração padronizada de PDFs no sistema
 * 
 * Esta classe encapsula a lógica de geração de PDFs, fornecendo métodos
 * que substituem as funções depreciadas e garantem a compatibilidade
 * entre diferentes versões do PHP.
 */
class PDFHelper {
    /**
     * Converte UTF-8 para ISO-8859-1 (Latin1) sem usar utf8_decode
     * 
     * @param string $str String em UTF-8
     * @return string String convertida para ISO-8859-1
     */
    public static function utf8ToLatin1($str) {
        // Se a extensão mbstring estiver disponível, usamos mb_convert_encoding
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        }
        
        // Fallback para iconv se disponível
        if (function_exists('iconv')) {
            $result = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
            return $result !== false ? $result : $str;
        }
        
        // Se nenhuma função de conversão estiver disponível, usamos um fallback simplificado
        // (não é perfeito, mas é melhor que usar utf8_decode depreciado)
        $chars = array(
            // Acentos
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            // Maiúsculas
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        );
        
        return strtr($str, $chars);
    }
    
    /**
     * Adiciona texto a um PDF, tratando codificação UTF-8 para ISO-8859-1
     * 
     * @param FPDF $pdf Objeto FPDF
     * @param float $x Posição X
     * @param float $y Posição Y
     * @param string $txt Texto UTF-8
     * @return void
     */
    public static function addText($pdf, $x, $y, $txt) {
        $pdf->Text($x, $y, self::utf8ToLatin1($txt));
    }
    
    /**
     * Cria uma célula em um PDF, tratando codificação UTF-8 para ISO-8859-1
     * 
     * @param FPDF $pdf Objeto FPDF
     * @param float $w Largura da célula
     * @param float $h Altura da célula
     * @param string $txt Texto UTF-8
     * @param int $border Borda (0: sem borda, 1: borda completa, etc.)
     * @param int $ln Posição após a célula (0: à direita, 1: início da próxima linha, 2: abaixo)
     * @param string $align Alinhamento ('L', 'C', 'R')
     * @param bool $fill Preenchimento (true/false)
     * @param string $link URL do link
     * @return void
     */
    public static function addCell($pdf, $w, $h, $txt, $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
        $pdf->Cell($w, $h, self::utf8ToLatin1($txt), $border, $ln, $align, $fill, $link);
    }
    
    /**
     * Cria uma célula de múltiplas linhas em um PDF, tratando codificação UTF-8 para ISO-8859-1
     * 
     * @param FPDF $pdf Objeto FPDF
     * @param float $w Largura da célula
     * @param float $h Altura da célula
     * @param string $txt Texto UTF-8
     * @param int $border Borda
     * @param string $align Alinhamento ('L', 'C', 'R', 'J')
     * @param bool $fill Preenchimento
     * @return void
     */
    public static function addMultiCell($pdf, $w, $h, $txt, $border = 0, $align = 'J', $fill = false) {
        $pdf->MultiCell($w, $h, self::utf8ToLatin1($txt), $border, $align, $fill);
    }
    
    /**
     * Verifica e garante que um valor numérico existe
     * 
     * @param array $arr Array a ser verificado
     * @param string $key Chave a ser verificada
     * @param array $alternateKeys Chaves alternativas a verificar
     * @param float $default Valor padrão
     * @return float Valor existente ou padrão
     */
    public static function ensureNumericValue($arr, $key, $alternateKeys = [], $default = 0) {
        if (isset($arr[$key]) && is_numeric($arr[$key])) {
            return $arr[$key];
        }
        
        foreach ($alternateKeys as $altKey) {
            if (isset($arr[$altKey]) && is_numeric($arr[$altKey])) {
                return $arr[$altKey];
            }
        }
        
        return $default;
    }
    
    /**
     * Verifica e garante que um valor string existe
     * 
     * @param array $arr Array a ser verificado
     * @param string $key Chave a ser verificada
     * @param array $alternateKeys Chaves alternativas a verificar
     * @param string $default Valor padrão
     * @return string Valor existente ou padrão
     */
    public static function ensureStringValue($arr, $key, $alternateKeys = [], $default = '') {
        if (isset($arr[$key]) && !empty($arr[$key])) {
            return $arr[$key];
        }
        
        foreach ($alternateKeys as $altKey) {
            if (isset($arr[$altKey]) && !empty($arr[$altKey])) {
                return $arr[$altKey];
            }
        }
        
        return $default;
    }
    
    /**
     * Inicia o buffer de saída e prepara cabeçalhos para PDF
     * 
     * @param string $filename Nome do arquivo PDF
     * @return void
     */
    public static function startPdfOutput($filename = 'documento.pdf') {
        // Limpa qualquer saída anterior
        if (ob_get_length()) ob_end_clean();
        
        // Inicia novo buffer
        ob_start();
        
        // Define cabeçalhos para PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    }
}
?>
