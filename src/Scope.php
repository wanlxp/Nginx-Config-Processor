<?php

/**
 * This file is part of the Nginx Config Processor package.
 *
 * (c) Roman Piták <roman@pitak.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace wanlxp\Nginx\Config;

class Scope extends Printable
{
    /** @var Directive $parentDirective */
    private $parentDirective = null;

    /** @var Directive[] $directives */
    private $directives = array();

    /** @var Printable[] $printables */
    private $printables = array();

    /**
     * Write this Scope into a file.
     *
     * @param $filePath
     * @throws Exception
     */
    public function saveToFile($filePath)
    {
        $handle = @fopen($filePath, 'w');
        if (false === $handle) {
            throw new Exception('Cannot open file "' . $filePath . '" for writing.');
        }

        $bytesWritten = @fwrite($handle, (string)$this);
        if (false === $bytesWritten) {
            fclose($handle);
            throw new Exception('Cannot write into file "' . $filePath . '".');
        }

        $closed = @fclose($handle);
        if (false === $closed) {
            throw new Exception('Cannot close file handle for "' . $filePath . '".');
        }
    }

    /*
     * ========== Factories ==========
     */

    /**
     * Provides fluid interface.
     *
     * @return Scope
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Create new Scope from the configuration string.
     *
     * @param \wanlxp\Nginx\Config\Text $configString
     * @return Scope
     * @throws Exception
     */
    public static function fromString(Text $configString)
    {
        $scope = new Scope();
        while (false === $configString->eof()) {

            if (true === $configString->isEmptyLine()) {
                $scope->addPrintable(EmptyLine::fromString($configString));
            }

            $char = $configString->getChar();

            if ('#' === $char) {
                $scope->addPrintable(Comment::fromString($configString));
                continue;
            }

            if (('a' <= $char) && ('z' >= $char)) {
                $scope->addDirective(Directive::fromString($configString));
                continue;
            }
			
			if (('A' <= $char) && ('Z' >= $char)) {
                $scope->addDirective(Directive::fromString($configString));
                continue;
            }


            if ('}' === $configString->getChar()) {
                break;
            }

            $configString->inc();
        }
        return $scope;
    }

    /**
     * Create new Scope from a file.
     *
     * @param $filePath
     * @return Scope
     */
    public static function fromFile($filePath)
    {
        return self::fromString(new File($filePath));
    }

    /*
     * ========== Getters ==========
     */

    /**
     * Get parent Directive.
     *
     * @return Directive|null
     */
    public function getParentDirective()
    {
        return $this->parentDirective;
    }

    /*
     * ========== Setters ==========
     */

    /**
     * Add a Directive to the list of this Scopes directives
     *
     * Adds the Directive and sets the Directives parent Scope to $this.
     *
     * @param Directive $directive
     * @return $this
     */
    public function addDirective(Directive $directive)
    {
        if ($directive->getParentScope() !== $this) {
            $directive->setParentScope($this);
        }

        $this->directives[] = $directive;
        $this->addPrintable($directive);

        return $this;
    }

    /**
     * Add printable element.
     *
     * @param Printable $printable
     */
    private function addPrintable(Printable $printable)
    {
        $this->printables[] = $printable;
    }
    
    private function delPrintable(Printable $printable)
    {
        for ($i = 0; $i < count($this->printables); $i++) {
            if ($printable == $this->printables[$i]) {
                array_splice($this->printables, $i, 1);
            }
        }
    }


    /**
     * Set parent directive for this Scope.
     *
     * Sets parent directive for this Scope and also
     * sets the $parentDirective->setChildScope($this)
     *
     * @param Directive $parentDirective
     * @return $this
     */
    public function setParentDirective(Directive $parentDirective)
    {
        $this->parentDirective = $parentDirective;

        if ($parentDirective->getChildScope() !== $this) {
            $parentDirective->setChildScope($this);
        }

        return $this;
    }

    /*
     * ========== Printable ==========
     */

    /**
     * Pretty print with indentation.
     *
     * @param $indentLevel
     * @param int $spacesPerIndent
     * @return string
     */
    public function prettyPrint($indentLevel, $spacesPerIndent = 4)
    {
        $resultString = "";
        foreach ($this->printables as $printable) {
            $resultString .= $printable->prettyPrint($indentLevel + 1, $spacesPerIndent);
        }

        return $resultString;
    }

	public function getDirectiveByKey($key) 
	{
		foreach($this->directives as $dir) {
			if ($key == $dir->getName()) {
				return $dir;
			}
		}
		return null;
	}

    /**
     * returns all directives searched by key as an array, for example return all server
     *
     * @param string $key
     * @return array
     */
	public function getAllDirectivesByKey($key) 
	{
        $reusult = array();
		foreach($this->directives as $dir) {
			if ($key == $dir->getName()) {
				$reusult[] = $dir;
			}
		}
		return $reusult;
	}

	public function getDirective($key)
	{
		$dir = null;
		$arr = explode('\\', $key);
		
		$scope = $this;
		foreach ($arr as $item) {
			$dir = $scope->getDirectiveByKey($item);
			if ($dir == null) {
				return '';
			}
			$scope = $dir->getChildScope();
		}
		return $dir;
	}

	public function getDirectiveValue($path)
	{
		$dir = $this->getDirective($path);
		if ($dir) {
			return $dir->getValue();
		}
		return $dir;
	}
	
	public function setDirectiveValue($path, $value)
	{
		$dir = $this->getDirective($path);
		if ($dir) {
			$dir->setValue($value);
		}
	}
    
    public function addDirectiveValue($path, $name, $value)
	{  
		$dir = $this->getDirective($path);
		if ($dir) {
            $dir->getChildScope()->addDirective(Directive::create($name, $value));
		}
	}

    
    public function delDirectiveValue($path, $value)
	{
        $parent;
        $i;
        $arr = explode('\\', $path);
        $arr = array_reverse($arr);


		$dir = $this->getDirective($path);
		if ($dir) {
            $parent = $dir->getParentScope();
            
            for ($i = 0; $i < count($parent->directives); $i++) {
                $tmp = $parent->directives[$i];
                if ($tmp->getName() == $arr[0] && $tmp->getValue() == $value) {
                    $parent->delPrintable($parent->directives[$i]);
                    array_splice($parent->directives, $i, 1);
                    return;
                }
            }
		}
	}
    
    public function delDirectiveValues($path)
	{
        $arr = explode('\\', $path);
        $arr = array_reverse($arr);

		$dir = $this->getDirective($path);
		if ($dir) {
            $parent = $dir->getParentScope();

            $parent->childScope = array();
            $parent->printables = array();
		}
	}

    
    public function getDirectiveValues($path)
	{
        $ret = array();
		$arr = explode('\\', $path);
        $arr = array_reverse($arr);

		$dir = $this->getDirective($path);
		if ($dir) {
            $parent = $dir->getParentScope();
            
            for ($i = 0; $i < count($parent->directives); $i++) {
                $tmp = $parent->directives[$i];
                if ($tmp->getName() == $arr[0]) {
                    $ret[] = $tmp->getValue();
                }
            }

		}
		return $ret;
	}



    public function __toString()
    {
        return $this->prettyPrint(-1);
    }
}
