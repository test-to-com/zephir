
namespace Test\BuiltIn;

class ArrayMethods
{
  const c = [];
  protected p = [];

	public function getJoin1()
	{
		return [1, 2, 3]->join("-");
	}

	public function getReversed1()
	{
		return [1, 2, 3]->reversed();
	}

	public function getMap1()
	{
		return [1, 2, 3]->map(x => x * 100);
	}

  public function return1() {
		return this->c->join("-");
  }

  public function helper(a) {
    return a;
  }

  public function methodcall1() {
		this->helper([1, 2, 3]->join("-"));
  }

  public function methodcall2() {
    array a = [1,2,3];
		this->helper(a->join("-"));
  }

  public function deadcode1() {
		[1, 2, 3]->join("-");
  }

  public function deadcode2() {
    array a = [1,2,3];
		a->join("-");
  }

  public function deadcode3() {
		this->p->join("-");
  }

  public function fcall1() {
    // Normal Join
    join(',', [1,2,3]);
  }

  public function fcall2() {
    // join as a function parameter
    substr([1,2,3]->join(","), 3); // '2,3'
  }

  public function fcall3() {
    // join as a function parameter
    substr("AAA".([1,2,3]->join(",")), 4); // '1,2,3'
  }
}