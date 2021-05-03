import subprocess
import os
import logging
from .command_line_driver import CommandLineDriver as Cli

class Kubectl(Cli):
    __pod_ids = []
    __use_all_pods = False
    __resource_type = None
    __resource_name = None

    __logger = None

    def __init__(self, resource_name: str, resource_type: str = "deployment"):
        self.__logger = logging.getLogger("kubectl")
        self.__resource_name = resource_name
        self.__resource_type = resource_type
        self.__load_pods()

    ###
    #
    ###
    def __load_pods(self):
        pod_id_command = f"""\
            kubectl get pods \
                --namespace=default \
                --field-selector=status.phase=Running \
                | grep {self.__resource_name} \
                | awk '{{print $1}}'\
            """
        self.__pod_ids = list(
            filter(
                None,
                subprocess.check_output(pod_id_command, shell=True, encoding="utf-8")
                .strip()
                .split("\n"),
            )
        )

        contexts = subprocess.check_output("kubectl config get-contexts", shell=True, encoding="utf-8")
        self.__logger.debug(contexts)

        self.__logger.debug("Collected pod IDs:")
        self.__logger.debug(self.__pod_ids)

        if len(self.__pod_ids) == 0:
            raise Exception(
                f"Could not find any available pods for the {self.__resource_type} {self.__resource_name}"
            )

    ###
    #
    ###
    def on_all_pods(self):
        self.__use_all_pods = True
        return self

    ###
    #
    ###
    def restart_pods(self):
        # For the time being use the alternative kubectl binary instead of the gcloud sdk controlled one
        command = f"/usr/bin/kubectl rollout restart --namespace=default {self.__resource_type} {self.__resource_name}"
        self.__logger.debug(f"Restart command: {command}")
        self.__logger.info(
            f"Restarting {self.__resource_type} {self.__resource_name}. This might take a while..."
        )
        restart_result = subprocess.check_output(command, shell=True, encoding="utf-8")
        self.__logger.debug(f"Restart result: {restart_result}")

        status_command = f"/usr/bin/kubectl rollout status --namespace=default {self.__resource_type} {self.__resource_name}"
        self.__logger.debug(f"Restart status command: {status_command}")
        status_result = subprocess.check_output(status_command, shell=True, encoding="utf-8")
        self.__logger.debug(f"Status result: {status_result}")

        self.__logger.info(f"Restart complete.")

    ###
    #
    ###
    def exec(self, command_str: str, container: str = None):
        # Do some single quote magic in command string to prevent invalid commands
        command_str = command_str.replace("'", "'\"'\"'")

        container_cmd = f"-c {container}" if container else ""

        single_result = not self.__use_all_pods

        self.__logger.debug(f"Kubectl instance id: {id(self)}")
        self.__logger.debug(self.__pod_ids)

        contexts = subprocess.check_output("kubectl config get-contexts", shell=True, encoding="utf-8")
        self.__logger.debug(contexts)

        results = []
        for pod in self.__get_pods():
            command = f"kubectl exec -t {container_cmd} --namespace=default {pod} -- /bin/bash -c '{command_str}'"
            self.__logger.debug(f"Kubectl exec command: {command}")
            results.append(subprocess.check_output(command, shell=True, encoding="utf-8"))

        result = results[0] if single_result else results

        return result

    ###
    #
    ###
    def copy_to(self, source: str, target: str = None, move: bool = False):

        for pod in self.__get_pods():
            copy_client_command = (
                f"kubectl cp --namespace=default {source} {pod}:{target or source}"
            )
            self.__logger.debug(f"{copy_client_command}")
            subprocess.check_output(copy_client_command, shell=True)

        if move:
            os.remove(source)

    ###
    #
    ###
    def copy_from(self, source: str, target: str = None, move: bool = False):
        use_all_pods = self.__use_all_pods

        for pod in self.__get_pods():
            copy_client_command = (
                f"kubectl cp --namespace=default {pod}:{source} {target or source}"
            )
            subprocess.check_output(copy_client_command, shell=True)

        if move:
            self.__use_all_pods = use_all_pods
            self.delete_file(source)

    ###
    #
    ###
    def delete_file(self, file: str):
        delete_command = f"rm {file}"
        return self.exec(delete_command)

    ###
    #
    ###
    def __get_pods(self) -> list:
        pods = self.__pod_ids[: len(self.__pod_ids) if self.__use_all_pods else 1]
        self.__use_all_pods = False
        return pods
