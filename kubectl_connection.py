import logging
import re

from .kubectl import Kubectl
from .db_connection import DatabaseConnection


###
# Concrete database connection using Kubectl
###
class KubectlConnection(DatabaseConnection):
    __cursor = None
    __kubectl = None
    __podname = None
    __logger = None

    def __init__(self, podname: str = "proactive-frame"):
        self.__podname = podname
        self.__logger = logging.getLogger("kubectl_conn")

    ###
    #
    ###
    def connect(self, conn_args: dict, autocommit: bool = False):
        self.__kubectl = Kubectl(self.__podname)
        self.__cursor = _KubectlCursor(self.__kubectl, conn_args)

    ###
    #
    ###
    def get_cursor(self):
        return self.__cursor

    ###
    #
    ###
    def commit(self):
        return self.__cursor.commit()

    ###
    #
    ###
    def close_connection(self):
        if self.__cursor:
            del self.__cursor


###
# Proxy Cursor class for Kubectl connections
###
class _KubectlCursor:
    __kubectl = None
    __connArgs = None

    rowcount = -1
    lastrowid = None

    __results = []

    __pointer = 0
    __logger = None

    def __init__(self, kubectl: Kubectl, conn_args: dict):
        self.__kubectl = kubectl
        self.__connArgs = conn_args
        self.__logger = logging.getLogger("kubectl_cursor")

    ###
    #
    ###
    def execute(self, query: str, multi: bool = False):
        self.rowcount = -1
        self.lastrowid = None

        # Reset the cursor to point to first index
        self.__pointer = 0
        self.__results = []

        # We need to make sure that the last_insert_id query is executed in the same connection
        if query.lstrip()[:6].lower() == "insert":
            query += "; SELECT LAST_INSERT_ID() AS `id`;"

        self.__logger.debug(query)

        cmd = self.__build_query_cmd(query)

        self.__logger.debug(f"Query command: {cmd}")

        result = self.__kubectl.exec(cmd)

        self.__logger.debug(f"Raw result: {result}")

        if query.lstrip()[:6].lower() in ["select", "insert"]:
            res = list(
                map(
                    lambda x: list(
                        map(
                            lambda z: z.strip(),
                            re.sub(
                                "[\s]*\|[\s]*",
                                "\t",
                                re.sub("^\|(.*)\|$", "\\1", x.strip()),
                            )
                            .strip()
                            .split("\t"),
                        )
                    ),  # Clean up the results into a two-dimensional array
                    re.findall(r"^[^\+].*", result, flags=re.MULTILINE),
                )
            )  # Only results containing the | character

            result = []
            for c in res[1:]:
                result.append(dict(zip(res[0], c)))

            self.__logger.debug(f"Processed result:")
            self.__logger.debug(result)

            self.rowcount = len(result)
            if query.lstrip()[:6].lower() == "insert":
                self.lastrowid = int(result[0].get("id"))

        self.__results = result

        return result

    ###
    #
    ###
    def commit(self):
        self.__logger.debug("Committing queries")
        return self.__results

    ###
    #
    ###
    def fetchall(self):
        return self.__results

    ###
    #
    ###
    def fetchone(self) -> dict:
        result = None

        if self.rowcount > self.__pointer:
            result = self.__results[self.__pointer]
            self.__pointer += 1

        return result

    ###
    #
    ###
    def __build_query_cmd(self, query):
        if '"' in query:
            raise Exception(
                "Sorry. With the Kubectl driver we cannot process double quotes in a query string"
            )

        self.__logger.debug(self.__connArgs.items())

        login = " ".join(f"--{k}='{v}'" for k, v in self.__connArgs.items() if v)

        # Use quotation magic to pass queries via the cli
        query = query.replace("'", '"')

        return f"mysql {login} -e '{query}'"
