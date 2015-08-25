import functools

def wrapper(func, num):
  return func(num)

def decorator_with_try(func, num):
  def _wrapper(*args, **kwargs):
    if func:
      try:
        func(num, *args, **kwargs)
      except:
        raise
  return _wrapper

def decorator_without_try(func, num):
  def _wrapper(*args, **kwargs):
    func(num, *args, **kwargs)
  return _wrapper

def test_add(num):
  return num

test_decorator_with_try = decorator_with_try(test_add, 1)
test_decorator_without_try = decorator_without_try(test_add, 1)
test_bind = functools.partial(test_add, 1)

if __name__ == '__main__':
  import timeit

  t0 = timeit.Timer("test_add(1)",
                    "from __main__ import test_add")
  t1 = timeit.Timer("wrapper(test_add, 1)",
                    "from __main__ import test_add, wrapper")
  t2 = timeit.Timer("test_decorator_with_try()",
                    "from __main__ import test_decorator_with_try")
  t3 = timeit.Timer("test_decorator_without_try()",
                    "from __main__ import test_decorator_without_try")
  t4 = timeit.Timer("test_bind()",
                    "from __main__ import test_bind")
  t5 = timeit.Timer("functools.partial(test_add, 1)()",
                    "from __main__ import test_add, functools")
  loop_times = 3000000
  print t0.timeit(number=loop_times)
  print t1.timeit(number=loop_times)
  print t2.timeit(number=loop_times)
  print t3.timeit(number=loop_times)
  print t4.timeit(number=loop_times)
  print t5.timeit(number=loop_times)
