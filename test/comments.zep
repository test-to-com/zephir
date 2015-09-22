
/* 
 * LICENSE
 */
namespace Test;


/*****************
 * Class Assign  *
 *****************/
class Assign
{
  
  /** Single Line PHP Doc */
	protected testVar { get, set, toString };

  // Comment Single Line
	protected myArray;

  /*
   * Multi Line
   */
	protected static testVarStatic;

  // * Single Line Not PHP Doc
	protected twoComments;

  /**
   * Function testAssign
   *
   * return integer a
   */
	public function testAssign1()
	{
    // Declare a
		int a;

    // 
		let a = 1;
    // *
		return a;
	}

  /**
   * Function testAssign2
   
   * return integer a
   */
	public function testAssign2()
	{
		int a;
    /*
     */
		let a = true;
    /*
     * *
     */
		return a;
	}

	public function testTrailing1()
	{
    int a=1;
    // Trailing Comment
	}

	public function testTrailing2()
	{
    int a = 1;
    if(a === 1) {
      echo "1";
      // Trailing Comment
    }
	}

	public function testempty()
	{
	}

  public function testif() {
    int a = 1;
    if(a === 1) {
      // 1
      echo "1";
    } elseif(a === 2) {
      // 2
      echo "2";
    } else {
      // default
      echo "default";
    }
  }

  public function testswitch() {
    int a = 1;
    switch(a) {
      case 1:
        // 1
        echo "1";
        break;
      case 2:
        // 2
        echo "2";
        break;
      default:
        // default
        echo "default";
    }
  }

}
