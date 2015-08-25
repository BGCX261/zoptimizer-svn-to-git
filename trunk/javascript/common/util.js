var zopt.common.util = function () {
  function isSubset(lhs, rhs) {
    var type = Object.prototype.toString.apply(lhs);
    if (type !== Object.prototype.toString.apply(rhs)) return false;
    if (type === "[object Function]") return true;  // skip the function
    if (type !== "[object Array]" && type !== "[object Object]") return (lhs === rhs);
    for (var attr in lhs) {
      if (lhs.hasOwnProperty(attr)) {
        if (!rhs.hasOwnProperty(attr)) return false;
        if (!isSubset(lhs[attr], rhs[attr])) return false;
      }
    }
    return true;
  }

  function isEqual(lhs, rhs) {
    return isSubset(lhs, rhs) && isSubset(rhs, lhs);
  }

  function isArray(obj) {
    return (Object.prototype.toString.apply(obj) === "[object Array]");
  }

  function inArray(elem, arr, deepCompare) {
    for (var i = 0; i < arr.length; ++i) {
      if ((!deepCompare && elem === arr[i]) || (deepCompare && isEqual(elem, arr[i]))) return i;
    }
    return -1;
  }

  function isString(obj) {
    return (Object.prototype.toString.apply(obj) === "[object String]");
  }

  function isNumber(obj) {
    return (Object.prototype.toString.apply(obj) === "[object Number]");
  }

  function isFunction(obj) {
    return (Object.prototype.toString.apply(obj) === "[object Function]");
  }

  function isBoolean(obj) {
    return (Object.prototype.toString.apply(obj) === "[object Boolean]");
  }

  function isDefined(obj) {
    return (typeof(obj) !== undefined && obj !== null);
  }
} ();

