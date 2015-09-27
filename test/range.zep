
/**
 * Arithmetic operations
 */

namespace Test;

class Range
{
	public function inclusive1()
	{
		return 0..10;
	}

	public function exclusive1()
	{
		return 0...10;
	}

	public function arrayCall1()
	{
		return (0...10)->join('-');
	}

	public function arrayCall2()
	{
    /* BUG: Parser gives priority to -> over range
     * this implies that this is equivalent to 1..(10->join('-'))
     * rather than the (0..10)->join('-')
     */
		return 0...10->join('-');
	}
}