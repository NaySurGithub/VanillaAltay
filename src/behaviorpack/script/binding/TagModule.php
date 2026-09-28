<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\ScriptRuntime;
use function in_array;
use function is_array;
use function is_string;

/**
 * The tag methods of Block, BlockPermutation and ItemStack.
 */
final class TagModule{

	private Interpreter $js;

	public function __construct(
		private ScriptRuntime $runtime,
		private ClassFactory $f,
		private ServerModule $s
	){
		$this->js = $runtime->js;
	}

	public function define() : void{
		$this->defineTags($this->s->block, "tag.block");
		$this->defineTags($this->s->permutation, "tag.permutation");
		$this->defineTags($this->s->itemStack, "tag.item");
	}

	private function defineTags(HostClass $class, string $request) : void{
		$js = $this->js;
		$this->f->method($class, "getTags", function(mixed $thisValue, array $args) use ($js, $request) : mixed{
			return $js->newArray($this->tags($thisValue, $request));
		});
		$this->f->method($class, "hasTag", function(mixed $thisValue, array $args) use ($js, $request) : mixed{
			return in_array($js->toString($args[0] ?? ""), $this->tags($thisValue, $request), true);
		}, 1);
	}

	/**
	 * @return list<string>
	 */
	private function tags(mixed $thisValue, string $request) : array{
		$tags = $this->s->raw($request, $this->s->fromJs($thisValue));
		if(!is_array($tags)){
			return [];
		}
		$result = [];
		foreach($tags as $tag){
			if(is_string($tag)){
				$result[] = $tag;
			}
		}
		return $result;
	}
}
