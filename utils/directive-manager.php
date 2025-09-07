<?php
/**
 * Title: Directive Manager
 * About: adds common directives to php tempaltes and allows you to define your own!
 */
class DirectiveManager
{
    private $directives = [];
    private $templateVars = [];

    /**
     * Set template variables for directive processing
     * @param array $vars
     */
    public function setTemplateVars($vars)
    {
        $this->templateVars = $vars;
    }

    /**
     * Get template variables
     * @return array
     */
    public function getTemplateVars()
    {
        return $this->templateVars;
    }

    public function register($name, callable $handler)
    {
        $this->directives[$name] = $handler;
    }

    public function parse($content)
    {
        foreach ($this->directives as $name => $handler) {
            $pattern = "/@{$name}(?:\(([^)]*)\))?\s*(.*?)\s*@\/{$name}/s";
            $content = preg_replace_callback($pattern, function ($matches) use ($handler) {
                return call_user_func($handler, $matches, $this->templateVars);
            }, $content);
        }

        return apply_filters('template_directive_parsed_content', $content);
    }

    public function getRegistered()
    {
        return array_keys($this->directives);
    }
}

class DirectiveParser
{
    private $manager;

    public function __construct()
    {
        $this->manager = new DirectiveManager();
        $this->registerBuiltinDirectives();

        do_action('register_template_directives', $this);
    }

    public function parse($content, $templateVars = [])
    {
        $this->manager->setTemplateVars($templateVars);
        return $this->manager->parse($content);
    }

    public function addDirective($name, callable $handler)
    {
        $this->manager->register($name, $handler);
    }

    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Evaluate PHP expression with template variables in scope
     * @param string $expression
     * @param array $vars
     * @return mixed
     */
    public function evaluateExpression($expression, $vars = [])
    {
        // Extract variables to current scope
        extract($vars);

        $result = null;
        eval ('$result = ' . $expression . ';');
        return $result;
    }

    private function registerBuiltinDirectives()
    {
        // Admin directive
        $this->manager->register('admin', function ($matches, $vars) {
            if (!current_user_can('manage_options')) {
                return '';
            }
            return $matches[2];
        });

        // User directive
        $this->manager->register('user', function ($matches, $vars) {
            if (!is_user_logged_in()) {
                return '';
            }
            return $matches[2];
        });

        // Role directive
        $this->manager->register('role', function ($matches, $vars) {
            $params = $matches[1] ?? '';

            if (empty($params)) {
                return '';
            }

            // Extract variables and evaluate
            extract($vars);
            $role = null;
            eval ('$role = ' . $params . ';');

            if (!$role || !current_user_can($role)) {
                return '';
            }
            return $matches[2];
        });

        // Guest directive
        $this->manager->register('guest', function ($matches, $vars) {
            if (is_user_logged_in()) {
                return '';
            }
            return $matches[2];
        });

        // Each directive for loops
        $this->manager->register('each', function ($matches, $vars) {
            $params = $matches[1] ?? '';
            $content = $matches[2];
            $output = '';

            if (empty($params)) {
                return '';
            }

            // Extract template variables and evaluate array
            extract($vars);
            $array = null;
            eval ('$array = ' . $params . ';');

            if (!is_array($array)) {
                return '';
            }

            foreach ($array as $index => $value) {
                $key = $index;

                // Create new variable context for this iteration
                $iterationVars = array_merge($vars, [
                    'key' => $key,
                    'value' => $value,
                    'index' => $index
                ]);

                // Process content with iteration variables
                $processedContent = $content;

                // Simple PHP execution with variables in scope
                ob_start();
                extract($iterationVars);
                eval ('?>' . $processedContent);
                $output .= ob_get_clean();
            }

            return $output;
        });

        // If directive
        $this->manager->register('if', function ($matches, $vars) {
            $condition = $matches[1] ?? '';
            $content = $matches[2];

            if (empty($condition)) {
                return '';
            }

            // Extract variables and evaluate condition
            extract($vars);
            $result = false;
            eval ('$result = ' . $condition . ';');

            return $result ? $content : '';
        });
    }
}

class Template
{
    private $path;
    private $content;
    private $parser;

    public function __construct($path, DirectiveParser $parser)
    {
        if (!file_exists($path)) {
            throw new Exception("Template file not found: {$path}");
        }

        $this->path = $path;
        $this->content = file_get_contents($path);
        $this->parser = $parser;
    }

    public function render()
    {
        // Capture variables from the template context
        $templateVars = $this->captureTemplateVariables();
        $parsed = $this->getParsedContent($templateVars);

        $tempFile = $this->createTempFile($parsed);

        // Include with original variables available
        extract($templateVars);
        include $tempFile;

        $this->cleanupTempFile($tempFile);
    }

    public function getParsedContent($templateVars = [])
    {
        $content = apply_filters('template_before_directive_parsing', $this->content, $this->path);
        $parsed = $this->parser->parse($content, $templateVars);
        return apply_filters('template_after_directive_parsing', $parsed, $this->path);
    }

    public function getPath()
    {
        return $this->path;
    }

    /**
     * Capture variables defined before directives in template
     * @return array
     */
    private function captureTemplateVariables()
    {
        // Execute PHP code before first directive to capture variables
        $phpCode = $this->extractInitialPhpCode();

        if (!$phpCode) {
            return [];
        }

        // Capture variables by executing the PHP code
        ob_start();
        eval ('?>' . $phpCode);
        ob_end_clean();

        // Get all defined variables (excluding superglobals)
        $allVars = get_defined_vars();
        $templateVars = [];

        foreach ($allVars as $name => $value) {
            if (!in_array($name, ['_GET', '_POST', '_COOKIE', '_SESSION', '_SERVER', '_ENV', '_FILES', 'GLOBALS'])) {
                $templateVars[$name] = $value;
            }
        }

        return $templateVars;
    }

    /**
     * Extract PHP code that appears before the first directive
     * @return string
     */
    private function extractInitialPhpCode()
    {
        // Find first directive
        if (preg_match('/@\w+/', $this->content, $matches, PREG_OFFSET_CAPTURE)) {
            $firstDirectivePos = $matches[0][1];
            return substr($this->content, 0, $firstDirectivePos);
        }

        return $this->content;
    }

    private function createTempFile($content)
    {
        $uploadDir = wp_upload_dir();
        $tempDir = $uploadDir['basedir'] . '/template-cache';

        if (!file_exists($tempDir)) {
            wp_mkdir_p($tempDir);
        }

        $tempFile = $tempDir . '/template_' . md5($this->path . $content) . '.php';
        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    private function cleanupTempFile($tempFile)
    {
        wp_schedule_single_event(time() + 300, 'cleanup_template_cache', [$tempFile]);
    }
}