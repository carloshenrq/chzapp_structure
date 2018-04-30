<?php
/**
 * BSD 3-Clause License
 * 
 * Copyright (c) 2018, Carlos Henrique
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 ** Redistributions of source code must retain the above copyright notice, this
 *  list of conditions and the following disclaimer.
 * 
 ** Redistributions in binary form must reproduce the above copyright notice,
 *  this list of conditions and the following disclaimer in the documentation
 *  and/or other materials provided with the distribution.
 * 
 ** Neither the name of the copyright holder nor the names of its
 *  contributors may be used to endorse or promote products derived from
 *  this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace Controller;

class Asset extends Controller
{
    /**
     * Diretório dos arquivos ASSET
     * @var string
     */
    private $assetDir;

    /**
     * Diretório IMG dentro de $assetDir
     * @var string
     */
    private $imgDir;

    /**
     * Diretório JS dentro de $assetDir
     * @var string
     */
    private $jsDir;

    /**
     * Diretório CSS dentro de $assetDir
     * @var string
     */
    private $cssDir;

    /**
     * Diretório HOOK dentro de /
     * @var string
     */
    private $hookDir;

    /**
     * @see Controller::init()
     */
    protected function init()
    {
        $this->assetDir = realpath(join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'assets']));

        $this->imgDir = realpath(join(DIRECTORY_SEPARATOR, [
            $this->assetDir, 'img'
        ]));
        $this->jsDir = realpath(join(DIRECTORY_SEPARATOR, [
            $this->assetDir, 'js'
        ]));
        $this->cssDir = realpath(join(DIRECTORY_SEPARATOR, [
            $this->assetDir, 'css'
        ]));

        if($this->getApplication()->canHook())
            $this->hookDir = realpath(join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'hooks']));
        else
            $this->hookDir = false;

        // Adiciona expressão regular para obter arquivos javascript.
        $this->addRouteRegexp('/^\/asset\/js\/(.*)$/i', '/asset/js/{file}');
        $this->addRouteRegexp('/^\/asset\/scss\/(.*)$/i', '/asset/scss/{file}');
        $this->addRouteRegexp('/^\/asset\/img\/(.*)$/i', '/asset/img/{file}');
    }

    /**
     * Rota para carregar as imagens.
     *
     * @param object $response
     * @param array $args
     *
     * @return object
     */
    public function img_GET($response, $args)
    {
    	// Obtém a imagem que será utilizada.
    	$img = join(DIRECTORY_SEPARATOR, [
    		$this->imgDir,
    		$args['file']
    	]);

    	// Obtém o conteúdo da imagem.
    	$imgContent = file_get_contents($img);

        // Retorna o conteúdo do arquivo SCSS.
        return $response->write($imgContent)
                        ->withHeader('Content-Type', 'image/png');
    }

    /**
     * Obtém um arquivo SCSS e devolve para a tela.
     *
     * @param object $response
     * @param array $args
     */
    public function scss_GET($response, $args)
    {
        // Arquivo de CSS para retorno
        $cssContent = $this->getScssFile($args['file'], true, [], $this->cssDir);

        // Retorna o conteúdo do arquivo SCSS.
        return $response->write($cssContent)
                        ->withHeader('Content-Type', 'text/css');
    }

    /**
     * Obtém o arquivo SCSS compilado para envio dos dados.
     *
     * @param string $cssFile Caminho para o arquivo CSS
     * @param bool $minify Identifica se o arquivo será minificado.
     * @param array $vars Variaveis definidas para troca nos arquivos.
     * @param string $importPath Caminho para os arquivos de include
     *
     * @return string
     */
    private function getScssFile($cssFile, $minify = true, $vars = [], $importPath = __DIR__)
    {
        $css = $cssFile;
        $cssFile = false;

        // Prefere o arquivo HOOK ao arquivo original...
        if($this->hookDir !== false)
        {
            $cssFile = realpath(join(DIRECTORY_SEPARATOR, [
                $this->hookDir,
                $css
            ]));
        }

        // Obtém o caminho para o arquivo a ser enviado.
        if($cssFile === false)
            $cssFile = realpath(join(DIRECTORY_SEPARATOR, [
                $this->cssDir,
                $css
            ]));

        return $this->getFileFromCache($cssFile,
                        file_get_contents($cssFile),
                        $minify,
                        $vars,
                        $importPath);
    }

    /**
     * Obtém um arquivo javascript e devolve para a tela.
     *
     * @param $response
     * @param $args
     */
    public function js_GET($response, $args)
    {
        $jsFile = $this->getJsFile($args['file']);

        // Obtém o conteúdo do arquivo JS
        return $response->write($jsFile)
                        ->withHeader('Content-Type', 'application/javascript');
    }

    /**
     * Obtém o arquivo JS para retornar em tela.
     *
     * @param string $jsFile
     *
     * @return string Conteudo do arquivo a ser retornado
     */
    private function getJsFile($jsFile)
    {
        $js = $jsFile;
        $jsFile = false;

        if($this->hookDir !== false)
        {
            $jsFile = realpath(join(DIRECTORY_SEPARATOR, [
                $this->hookDir,
                $js
            ]));
        }

        if($jsFile === false)
            $jsFile = realpath(join(DIRECTORY_SEPARATOR, [
                $this->jsDir,
                $js
            ]));

        return $this->getFileFromCache($jsFile, file_get_contents($jsFile));
    }

    /**
     * Obtém o arquivo do cache interno gerado.
     *
     * @param string $file
     * @param string $fileContent
     *
     * @return string
     */
    private function getFileFromCache($file, $fileContent, $minify = true, $vars = [], $importPath = __DIR__)
    {
        return $this->getAssetParser()
                           ->getSqlCache()
                           ->parseFileFromCache($file, $fileContent, $minify, $vars, $importPath);
    }

    /**
     * Obtém o tratador de assets para o controller.
     *
     * @return \CHZApp\AssetParser
     */
    private function getAssetParser()
    {
        return $this->getApplication()->getAssetParser();
    }
}
