<?php

declare(strict_types=1);

namespace dimension\generator\structure\endcity;

use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructureBuffer;
use dimension\generator\structure\template\Rotation;
use dimension\generator\structure\template\StructureTemplate;

/**
 * One template of an end city, turned by a quarter-turn count and placed at
 * a template position (its minimum corner).
 */
final class EndCityPiece{

	private BoundingBox $boundingBox;
	private int $genDepth = 0;
	private StructureTemplate $template;
	private int $sizeX;
	private int $sizeZ;

	public function __construct(
		private string $templateName,
		StructureTemplate $baseTemplate,
		private int $x,
		private int $y,
		private int $z,
		private int $rotation,
		private bool $overwrite
	){
		$this->sizeX = $baseTemplate->getSizeX();
		$this->sizeZ = $baseTemplate->getSizeZ();
		$this->template = $rotation === Rotation::NONE ? $baseTemplate : $baseTemplate->rotate(Rotation::inverse($rotation), $rotation);
		$this->boundingBox = new BoundingBox(
			$x,
			$y,
			$z,
			$x + $this->template->getSizeX() - 1,
			$y + $this->template->getSizeY() - 1,
			$z + $this->template->getSizeZ() - 1
		);
	}

	public function getTemplateName() : string{
		return $this->templateName;
	}

	public function move(int $x, int $y, int $z) : void{
		$this->x += $x;
		$this->y += $y;
		$this->z += $z;
		$this->boundingBox->move($x, $y, $z);
	}

	/**
	 * Writes the template into $buffer. Pieces that do not overwrite leave
	 * the blocks under their air cells untouched.
	 */
	public function place(StructureBuffer $buffer) : void{
		$template = $this->template;
		$volume = $template->getVolume();
		for($index = 0; $index < $volume; ++$index){
			$state = $template->stateAt($index);
			if($state === null){
				continue;
			}
			if(!$this->overwrite && $state->getName() === StructureTemplate::AIR){
				continue;
			}
			$buffer->set($this->x + $template->xOf($index), $this->y + $template->yOf($index), $this->z + $template->zOf($index), $state);
		}
	}

	public function getRotation() : int{
		return $this->rotation;
	}

	public function getSizeX() : int{
		return $this->sizeX;
	}

	public function getSizeZ() : int{
		return $this->sizeZ;
	}

	public function getBoundingBox() : BoundingBox{
		return $this->boundingBox;
	}

	public function getX() : int{
		return $this->x;
	}

	public function getY() : int{
		return $this->y;
	}

	public function getZ() : int{
		return $this->z;
	}

	public function getGenDepth() : int{
		return $this->genDepth;
	}

	public function setGenDepth(int $genDepth) : void{
		$this->genDepth = $genDepth;
	}
}
