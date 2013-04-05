<?php

class PageWidgetRow extends ViewableData {

	private $widgets;
	public static $num_cols = 4;
	public $extraCSSClasses = '';

	public function __construct( $widgets = null ) {
		$this->widgets = ($widgets ? $widgets : array());
	}

	public function Widgets() {
		return array_reverse($this->widgets);
	}

	static public function build( Page $page, $extraWidgets = null ) {
		$rows = array();
		$widgets = $page->Widgets(null, '`Row` ASC, `Column` DESC');
		foreach( $widgets as $widget ) { /* @var $widget PageWidget */
			if( $widget->isGridWidget() ) {
				if( !$row = @$rows[$widget->Row] ) {
					$row = $rows[$widget->Row] = new PageWidgetRow();
				}
				$row->widgets[$widget->Column] = $widget;
			}
		}
		if( $extraWidgets ) {
			foreach( $extraWidgets as $rowNum => $widgets ) {
				foreach( $widgets as $col => $widget ) {
					if( !$row = @$rows[$rowNum] ) {
						$row = $rows[$rowNum] = new PageWidgetRow();
					}
					$row->widgets[$col] = $widget;
				}
			}
		}
		// create the empty rows
		ksort($rows);
		if( $keys = array_keys($rows) ) {
			$maxRow = array_pop($keys);
			for( $row = 1; $row <= $maxRow; $row++ ) {
				if( !isset($rows[$row]) ) {
					$rows[$row] = new PageWidgetRow();
				}
			}
		}
		// create the empty cells
		ksort($rows);
		$rowObjects = array();
		foreach( $rows as $rowNum => $row ) {
			ksort($row->widgets);
			
			$empty_left = 0;	//how many cells on the left are empty
			$empty_right = 0;	//	ditto right
			
			if( $keys = array_keys($row->widgets) ) {
				$minCell = $keys ? array_shift($keys) : 1;
				$maxCell = self::$num_cols;
				for( $cell = $minCell; $cell <= $maxCell;  ) {
					if( @!$row->widgets[$cell] ) {
						
						$row->widgets[$cell] = new FakeWidget();
						
						//use CSS margins to simulate placeholders at 
						//	left and right, not placeholder divs:
						if ($cell - $minCell <= $empty_left)
							$empty_left++;
						else if ($maxCell - $cell <= $empty_right)
							$empty_right++;								
						else //use a placeholder if it's in the middle
							$row->widgets[$cell] = new EmptyWidget();
												
					}
					$cell += $row->widgets[$cell]->ColSpan();
				}
				
				if ($empty_left > 0)
					$row->addCSSClass('PadLeft' . $empty_left);
				
				if ($empty_right > 0)
					$row->addCSSClass('PadRight' . $empty_right);
				
			}
		}
		
		// adjust for row span and add class to the page if we have widgets in cols 1 or 2
		$adjust = array();
		$minCol = -1;
		$delta = 0;
		foreach( $rows as $rowNum => $row ) {
			$rowSpan = $row->RowSpan();
			$minspan = 9999;
			foreach( $row->widgets as $colNum => $widget ) {
				if( ($minCol == -1) || ($colNum < $minCol) ) {
					$minCol = $colNum;
				}
				
				/* we need to take the minimum rowspan into account
				 * 		i.e when we say 'rowspan' here we actually mean
				 * 		'difference between the height of cells in this 
				 * 		row'. 
				 * 		e.g: A row with 3 cells with rowspans 2,2,3 has a 
				 * 			'RowSpan' of 1, not 3.
				 */	
				if ($widget->RowSpan() < $minspan) 
					$minspan = $widget->RowSpan(); 
				
			}
			if ($minspan > 1)
				$rowSpan -= ($minspan-1);
			
			if( $rowSpan > 1) {
				for( $i = $rowNum + 1; isset($rows[$i]); $i++ ) {
					
					if( sizeof($rows[$i]->widgets) ) {
						$rows[$i]->addCSSClass('afterRowSpan'.$rowSpan);
						$rowSpan -= $rows[$i]->RowSpan();
						if( $rowSpan == 0 ) {
							break;
						}
					}
				}
			}
		}
		if( $minCol <= 2 ) {
			$page->CSSClasses .= ' gridFullWidth';
		}
		//* debug */ self::debug_rows($rows);
		return $rows;
	}

	public function RowSpan() {
		$rowSpan = 1;
		foreach( $this->widgets as $colNum => $widget ) {
			if( $widget->RowSpan() > $rowSpan ) {
				$rowSpan = $widget->RowSpan();
			}
		}
		return $rowSpan;
	}

	public static function debug_rows( $rows ) {
		echo "<ul>\n";
		ksort($rows);
		foreach( $rows as $rowNum => $row ) {
			echo "<li>Row: $rowNum: ".get_class($row)."\n<ul>\n";
			ksort($row->widgets);
			foreach( $row->widgets as $col => $widget ) {
				echo "<li>Cel: $col: $widget: ".get_class($widget)."</li>\n";
			}
			echo "</ul></li>\n";
		}
		echo "</ul><hr/>\n";
	}

	public function obj($fieldName, $arguments = null, $forceReturnedObject = true, $cache = false, $cacheName = null) {
		return parent::obj($fieldName, $arguments, false, $cache, $cacheName);
	}

	public function addCSSClass( $class ) {
		$this->extraCSSClasses .= ($this->extraCSSClasses ? ' ' : '').$class;
	}

	public function CSSClasses() {
		return $this->extraCSSClasses;
	}

}

/**
 * An empty widget - renders a placeholder element for spacing.
 * @author antisol
 */
class EmptyWidget extends ViewableData {

	public function Widget() {
		return $this->renderWith(get_class($this));
	}

	public function ColSpan() {
		return 1;
	}

	public function RowSpan() {
		return 1;
	}
	
	public function CSSClasses() {
		return 'placeholder';
	}

}


/**
 * Like an EmptyWidget, but renders no content at all
 * @author antisol
 */
class FakeWidget extends EmptyWidget {
	public function Widget() {
		return '';
	}
	public function CSSClasses() {
		return '';
	}
}